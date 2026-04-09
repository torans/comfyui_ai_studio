<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 工作流模板管理控制器
 * 提供工作流的增删改查以及启动/停止功能
 */
class WorkflowTemplateController extends Controller
{
    /**
     * 类型到中文标签的映射
     */
    private const TYPE_LABELS = [
        't2i' => '文生图',
        'i2i' => '图生图',
        't2v' => '文生视频',
        'i2v' => '图生视频',
        'other' => '其他',
    ];

    /**
     * 获取工作流列表（支持按类型筛选）
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkflowTemplate::query();

        // 只返回激活的工作流
        $query->where('is_active', true);

        // 按类型筛选
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $workflows = $query->latest()->get();

        // 格式化为 API 返回格式
        $formatted = $workflows->map(function ($workflow) {
            return [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'code' => $workflow->code,
                'category' => $workflow->type,
                'category_label' => self::TYPE_LABELS[$workflow->type] ?? $workflow->type,
                'description' => $workflow->name,
                'version' => $workflow->version,
                'is_active' => $workflow->is_active,
                'parameter_schema' => $workflow->parameter_schema_json ?? [],
            ];
        });

        return response()->json($formatted);
    }

    /**
     * 获取单个工作流详情
     */
    public function show(WorkflowTemplate $workflowTemplate): JsonResponse
    {
        return response()->json([
            'id' => $workflowTemplate->id,
            'name' => $workflowTemplate->name,
            'code' => $workflowTemplate->code,
            'category' => $workflowTemplate->type,
            'category_label' => self::TYPE_LABELS[$workflowTemplate->type] ?? $workflowTemplate->type,
            'description' => $workflowTemplate->name,
            'version' => $workflowTemplate->version,
            'workflow_json' => $workflowTemplate->definition_json,
            'parameter_schema' => $workflowTemplate->parameter_schema_json ?? [],
        ]);
    }
}
