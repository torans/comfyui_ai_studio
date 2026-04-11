<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = WorkflowTemplate::query()->with(['creator:id,name']);

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

    public function start(WorkflowTemplate $workflow)
    {
        $workflow->update(['is_active' => true]);
        return redirect()->route('admin.workflows')->with('success', '工作流已启动');
    }

    public function stop(WorkflowTemplate $workflow)
    {
        $workflow->update(['is_active' => false]);
        return redirect()->route('admin.workflows')->with('success', '工作流已停止');
    }
}
