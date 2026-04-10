<?php

namespace App\Http\Controllers\Api;

use App\Actions\Generation\CreateGenerationJobAction;
use App\Events\GenerationJobStatusChanged;
use App\Http\Controllers\Controller;
use App\Jobs\DispatchGenerationJob;
use App\Models\GenerationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationJobController extends Controller
{
    /**
     * 获取任务列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = GenerationJob::query()
            ->where('user_id', $request->user()->id)
            ->with(['workflowTemplate:id,name,code,type', 'assets']);

        // 按状态筛选
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // 按类型筛选
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $jobs = $query->latest()->paginate(20);

        // 格式化为 API 返回格式
        $formatted = $jobs->map(function ($job) {
            return [
                'id' => $job->id,
                'workflow_name' => $job->workflowTemplate?->name,
                'workflow_code' => $job->workflowTemplate?->code,
                'type' => $job->type,
                'status' => $job->status,
                'progress' => $job->progress,
                'error_message' => $job->error_message,
                'assets' => $job->assets->map(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'type' => $asset->type,
                        'media_kind' => $asset->media_kind,
                        'filename' => $asset->filename,
                        'url' => $asset->resolved_url,
                    ];
                }),
                'created_at' => $job->created_at?->toISOString(),
                'started_at' => $job->started_at?->toISOString(),
                'finished_at' => $job->finished_at?->toISOString(),
            ];
        });

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    /**
     * 获取任务详情
     */
    public function show(GenerationJob $generationJob): JsonResponse
    {
        // 确保只能查看自己的任务
        if ($generationJob->user_id !== request()->user()->id) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $generationJob->load(['workflowTemplate:id,name,code,type', 'assets']);

        return response()->json([
            'job_id' => $generationJob->id,
            'workflow_id' => $generationJob->workflow_template_id,
            'workflow_name' => $generationJob->workflowTemplate?->name,
            'workflow_code' => $generationJob->workflowTemplate?->code,
            'category' => $generationJob->type,
            'status' => $generationJob->status,
            'inputs' => $generationJob->input_json,
            'result' => $generationJob->resolved_workflow_json,
            'error_message' => $generationJob->error_message,
            'comfy_prompt_id' => $generationJob->comfy_prompt_id,
            'assets' => $generationJob->assets->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'type' => $asset->type,
                    'media_kind' => $asset->media_kind,
                    'filename' => $asset->filename,
                    'url' => $asset->resolved_url,
                ];
            }),
            'created_at' => $generationJob->created_at?->toISOString(),
            'started_at' => $generationJob->started_at?->toISOString(),
            'finished_at' => $generationJob->finished_at?->toISOString(),
        ]);
    }

    /**
     * 创建任务
     */
    public function store(Request $request, CreateGenerationJobAction $action): JsonResponse
    {
        $data = $request->validate([
            'workflow_id' => ['required', 'integer'],
            'inputs' => ['required', 'array'],
            'client_request_id' => ['nullable', 'string'],
        ]);

        $job = $action->handle($request->user(), $data);

        // 立即广播 queued 状态
        event(new GenerationJobStatusChanged($job));

        // 投递队列任务
        DispatchGenerationJob::dispatch($job->id);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
        ], 201);
    }
}
