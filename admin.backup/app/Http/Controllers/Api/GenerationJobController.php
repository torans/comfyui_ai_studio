<?php

namespace App\Http\Controllers\Api;

use App\Actions\Generation\CreateGenerationJobAction;
use App\Http\Controllers\Controller;
use App\Models\GenerationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = GenerationJob::query()
            ->where('user_id', $request->user()->id)
            ->with('workflowTemplate')
            ->latest()
            ->paginate(20);

        return response()->json($jobs);
    }

    public function store(Request $request, CreateGenerationJobAction $action): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:t2i,i2i,i2v'],
            'workflow_code' => ['required', 'string'],
            'inputs' => ['required', 'array'],
        ]);

        $job = $action->handle($request->user(), $data);

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
        ], 201);
    }
}
