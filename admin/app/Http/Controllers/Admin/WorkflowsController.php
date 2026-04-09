<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 工作流管理页面控制器
 */
class WorkflowsController extends Controller
{
    /**
     * 显示工作流管理页面
     */
    public function index(Request $request): Response
    {
        $query = WorkflowTemplate::query()->with(['creator:id,name']);

        // 按类型筛选
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $workflows = $query->latest()->paginate(20);

        return Inertia::render('admin/workflows', [
            'workflows' => $workflows,
            'filters' => [
                'type' => $request->type,
            ],
        ]);
    }
}
