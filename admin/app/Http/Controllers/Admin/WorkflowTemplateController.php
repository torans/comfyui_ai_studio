<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

/**
 * 工作流模板管理页面控制器
 */
class WorkflowTemplateController extends Controller
{
    /**
     * 显示创建工作流页面
     */
    public function create(): Response
    {
        return Inertia::render('admin/workflows/create', [
            'types' => [
                ['value' => 't2i', 'label' => '文生图'],
                ['value' => 'i2i', 'label' => '图生图'],
                ['value' => 't2v', 'label' => '文生视频'],
                ['value' => 'i2v', 'label' => '图生视频'],
                ['value' => 'other', 'label' => '其他工具'],
            ],
        ]);
    }

    /**
     * 显示编辑工作流页面
     */
    public function edit(WorkflowTemplate $workflowTemplate): Response
    {
        return Inertia::render('admin/workflows/edit', [
            'workflow' => [
                'id' => $workflowTemplate->id,
                'name' => $workflowTemplate->name,
                'code' => $workflowTemplate->code,
                'type' => $workflowTemplate->type,
                'version' => $workflowTemplate->version,
                'definition_json' => $workflowTemplate->definition_json,
                'parameter_schema_json' => $workflowTemplate->parameter_schema_json,
                'is_active' => $workflowTemplate->is_active,
            ],
            'types' => [
                ['value' => 't2i', 'label' => '文生图'],
                ['value' => 'i2i', 'label' => '图生图'],
                ['value' => 't2v', 'label' => '文生视频'],
                ['value' => 'i2v', 'label' => '图生视频'],
                ['value' => 'other', 'label' => '其他工具'],
            ],
        ]);
    }

    /**
     * 保存新工作流
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:workflow_templates,code'],
            'type' => ['required', 'in:t2i,i2i,t2v,i2v,other'],
            'version' => ['required', 'string', 'max:50'],
            'definition_json' => ['required', 'array'],
            'parameter_schema_json' => ['nullable', 'array'],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = true;

        WorkflowTemplate::create($data);

        return redirect()->route('admin.workflows')->with('success', '工作流创建成功');
    }

    /**
     * 更新工作流
     */
    public function update(Request $request, WorkflowTemplate $workflowTemplate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:100', 'unique:workflow_templates,code,' . $workflowTemplate->id],
            'type' => ['sometimes', 'in:t2i,i2i,t2v,i2v,other'],
            'version' => ['sometimes', 'string', 'max:50'],
            'definition_json' => ['sometimes', 'array'],
            'parameter_schema_json' => ['nullable', 'array'],
        ]);

        $data['updated_by'] = $request->user()->id;
        $workflowTemplate->update($data);

        return redirect()->route('admin.workflows')->with('success', '工作流更新成功');
    }
}
