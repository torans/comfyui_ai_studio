import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

interface PageProps {
    types: TypeOption[];
}

export default function CreateWorkflow() {
    const { types } = usePage<PageProps>().props;
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        code: '',
        type: 't2i',
        version: '1.0.0',
        definition_json: '{}',
        parameter_schema_json: '{}',
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        try {
            // 解析 JSON 字符串
            const data = {
                ...formData,
                definition_json: JSON.parse(formData.definition_json || '{}'),
                parameter_schema_json: JSON.parse(formData.parameter_schema_json || '{}'),
            };

            router.post('/admin/workflows', data, {
                onFinish: () => setSaving(false),
            });
        } catch {
            alert('JSON 格式错误，请检查输入');
            setSaving(false);
        }
    };

    return (
        <>
            <Head title="创建工作流" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">创建工作流</h1>
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

                            <div className="grid gap-2">
                                <Label htmlFor="type">类型 *</Label>
                                <Select value={formData.type} onValueChange={(value) => setFormData({ ...formData, type: value })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.map((type) => (
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
                                placeholder='{"69": {"inputs": {"prompt": "{{prompt}}"}}}'
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
                                placeholder='{"prompt": {"node": "69", "field": "prompt", "label": "提示词", "type": "textarea"}}'
                                className="font-mono min-h-[200px]"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-4">
                        <Button variant="outline" type="button" onClick={() => router.visit('/admin/workflows')}>
                            取消
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? '创建中...' : '创建工作流'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
