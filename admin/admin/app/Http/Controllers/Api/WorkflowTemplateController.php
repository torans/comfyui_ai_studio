<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTemplateController extends Controller
{
    private const TYPE_LABELS = [
        't2i' => '文生图',
        'i2i' => '图生图',
        't2v' => '文生视频',
        'i2v' => '图生视频',
        'other' => '其他',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = WorkflowTemplate::query();

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $workflows = $query->latest()->get();

        $formatted = $workflows->map(function ($workflow) {
            return [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'code' => $workflow->code,
                'type' => $workflow->type,
                'category' => $workflow->type,
                'category_label' => self::TYPE_LABELS[$workflow->type] ?? $workflow->type,
                'description' => $workflow->name,
                'version' => $workflow->version,
                'is_active' => $workflow->is_active,
                'parameter_schema' => $this->normalizeSchema($workflow->parameter_schema_json ?? []),
            ];
        });

        return response()->json($formatted);
    }

    public function show(WorkflowTemplate $workflowTemplate): JsonResponse
    {
        return response()->json([
            'id' => $workflowTemplate->id,
            'name' => $workflowTemplate->name,
            'code' => $workflowTemplate->code,
            'type' => $workflowTemplate->type,
            'category' => $workflowTemplate->type,
            'category_label' => self::TYPE_LABELS[$workflowTemplate->type] ?? $workflowTemplate->type,
            'description' => $workflowTemplate->name,
            'version' => $workflowTemplate->version,
            'workflow_json' => $workflowTemplate->definition_json,
            'parameter_schema' => $this->normalizeSchema($workflowTemplate->parameter_schema_json ?? []),
        ]);
    }

    public function start(WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $workflowTemplate->update(['is_active' => true]);

        return response()->json([
            'message' => '工作流已启动',
            'is_active' => true,
        ]);
    }

    public function stop(WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $workflowTemplate->update(['is_active' => false]);

        return response()->json([
            'message' => '工作流已停止',
            'is_active' => false,
        ]);
    }

    private function normalizeSchema(mixed $schema): array
    {
        if (is_string($schema) && $schema !== '') {
            $decoded = json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeSchema($decoded);
            }
            return [];
        }

        if (!is_array($schema)) {
            return [];
        }

        if (array_is_list($schema)) {
            return array_values(array_filter($schema, fn ($item) => is_array($item)));
        }

        $normalized = [];
        foreach ($schema as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            if (!isset($config['input_key']) && is_string($key) && $key !== '') {
                $config['input_key'] = $key;
            }

            $normalized[] = $config;
        }

        return $normalized;
    }
}
