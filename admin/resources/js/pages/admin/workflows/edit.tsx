import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkflowThumbUploadField } from '@/components/workflow-thumb-upload-field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';

interface TypeOption {
    value: string;
    label: string;
}

interface Workflow {
    id: number;
    name: string;
    description?: string | null;
    thumb?: string | null;
    code: string;
    type: string;
    version: string;
    definition_json: Record<string, unknown>;
    parameter_schema_json: Record<string, unknown>;
}

interface PageProps {
    workflow: Workflow;
    types: TypeOption[];
}

export default function EditWorkflow() {
    const { workflow, types } = usePage<PageProps>().props;
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        name: workflow?.name || '',
        description: workflow?.description || '',
        thumb: workflow?.thumb || '',
        code: workflow?.code || '',
        type: workflow?.type || 't2i',
        version: workflow?.version || '1.0.0',
        definition_json: workflow?.definition_json ? JSON.stringify(workflow.definition_json, null, 2) : '{}',
        parameter_schema_json: workflow?.parameter_schema_json ? JSON.stringify(workflow.parameter_schema_json, null, 2) : '{}',
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        try {
            const data = {
                ...formData,
                definition_json: JSON.parse(formData.definition_json || '{}'),
                parameter_schema_json: JSON.parse(formData.parameter_schema_json || '{}'),
            };

            router.put(`/admin/workflows/${workflow.id}`, data, {
                onFinish: () => setSaving(false),
            });
        } catch {
            alert('JSON 格式错误，请检查输入');
            setSaving(false);
        }
    };

    return (
        <>
            <Head title="编辑工作流" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">编辑工作流</h1>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <div className="grid gap-6 rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="text-lg font-semibold">基本信息</h2>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">名称 *</Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="例如：默认文生图"
                                    required
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code">编码 *</Label>
                                <Input
                                    id="code"
                                    value={formData.code}
                                    onChange={(e) => setFormData({ ...formData, code: e.target.value.toLowerCase().replace(/\s+/g, '_') })}
                                    placeholder="例如：t2i_default"
                                    required
                                />
                            </div>

                            <WorkflowThumbUploadField
                                value={formData.thumb}
                                onChange={(thumb) => setFormData({ ...formData, thumb })}
                                disabled={saving}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="description">描述</Label>
                                <Textarea
                                    id="description"
                                    value={formData.description}
                                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                    placeholder="一句话描述这个工作流的用途"
                                    className="min-h-[100px]"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="type">类型 *</Label>
                                <Select value={formData.type} onValueChange={(value) => setFormData({ ...formData, type: value })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types?.map((type: TypeOption) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="version">版本</Label>
                                <Input
                                    id="version"
                                    value={formData.version}
                                    onChange={(e) => setFormData({ ...formData, version: e.target.value })}
                                    placeholder="例如：1.0.0"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-6 rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="text-lg font-semibold">工作流 JSON</h2>
                        <p className="text-sm text-muted-foreground">
                            粘贴 ComfyUI 导出的工作流 JSON。动态变量使用 {"{{variable}}"} 占位。
                        </p>

                        <div className="grid gap-2">
                            <Label htmlFor="definition_json">工作流定义 *</Label>
                            <Textarea
                                id="definition_json"
                                value={formData.definition_json}
                                onChange={(e) => setFormData({ ...formData, definition_json: e.target.value })}
                                className="font-mono min-h-[300px]"
                                required
                            />
                        </div>
                    </div>

                    <div className="grid gap-6 rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="text-lg font-semibold">参数配置</h2>
                        <p className="text-sm text-muted-foreground">
                            定义动态变量的映射关系。示例：
                            <br />
                            {'{"prompt": {"node": "69", "field": "prompt", "label": "提示词", "type": "textarea"}}'}
                        </p>

                        <div className="grid gap-2">
                            <Label htmlFor="parameter_schema_json">参数 Schema</Label>
                            <Textarea
                                id="parameter_schema_json"
                                value={formData.parameter_schema_json}
                                onChange={(e) => setFormData({ ...formData, parameter_schema_json: e.target.value })}
                                className="font-mono min-h-[200px]"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-4">
                        <Button variant="outline" type="button" onClick={() => router.visit('/admin/workflows')}>
                            取消
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? '保存中...' : '保存修改'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
