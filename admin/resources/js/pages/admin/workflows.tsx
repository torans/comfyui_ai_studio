import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

/**
 * 工作流类型映射（中文显示）
 */
const WORKFLOW_TYPE_LABELS: Record<string, string> = {
    t2i: '文生图',
    i2i: '图生图',
    t2v: '文生视频',
    i2v: '图生视频',
    other: '其他工具',
};

const resolveThumbPreviewUrl = (value?: string | null): string | null => {
    if (!value) {
        return null;
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:')) {
        return value;
    }

    return value.startsWith('/') ? value : `/${value}`;
};

interface WorkflowTemplate {
    id: number;
    name: string;
    description?: string | null;
    thumb?: string | null;
    code: string;
    type: string;
    version: string;
    is_active: boolean;
    created_at: string;
    creator?: {
        name: string;
    };
}

interface PageProps {
    workflows: {
        data: WorkflowTemplate[];
        links: Record<string, string>;
    };
    filters?: {
        type?: string;
    };
}

export default function Workflows() {
    const { workflows } = usePage<PageProps>().props;
    const [loading, setLoading] = useState<number | null>(null);

    const handleStart = async (id: number) => {
        setLoading(id);
        try {
            router.post(`/admin/workflows/${id}/start`);
            window.location.reload();
        } catch {
            alert('启动失败');
        } finally {
            setLoading(null);
        }
    };

    const handleStop = async (id: number) => {
        setLoading(id);
        try {
            router.post(`/admin/workflows/${id}/stop`);
            window.location.reload();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            alert(err.response?.data?.message || '停止失败');
        } finally {
            setLoading(null);
        }
    };

    return (
        <>
            <Head title="工作流管理" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">工作流管理</h1>
                    <Button onClick={() => router.visit('/admin/workflows/create')}>
                        添加工作流
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 overflow-hidden">
                    <table className="w-full">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left text-sm font-medium">ID</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">名称</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">编码</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">类型</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">版本</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">状态</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">创建者</th>
                                <th className="px-4 py-3 text-right text-sm font-medium">操作</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {workflows.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-muted-foreground">
                                        暂无工作流
                                    </td>
                                </tr>
                            ) : (
                                workflows.data.map((workflow) => (
                                    <tr key={workflow.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono text-sm">{workflow.id}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="h-12 w-12 overflow-hidden rounded-xl border border-border bg-muted/40">
                                                    {resolveThumbPreviewUrl(workflow.thumb) ? (
                                                        <img
                                                            src={resolveThumbPreviewUrl(workflow.thumb) || undefined}
                                                            alt={workflow.name}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center text-xs text-muted-foreground">
                                                            无图
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">{workflow.name}</div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {workflow.description || '未填写描述'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-sm">{workflow.code}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {WORKFLOW_TYPE_LABELS[workflow.type] || workflow.type}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-sm">{workflow.version}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={workflow.is_active ? 'default' : 'secondary'}>
                                                {workflow.is_active ? '运行中' : '已停止'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-sm">{workflow.creator?.name || '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => router.visit(`/admin/workflows/${workflow.id}/edit`)}
                                                >
                                                    编辑
                                                </Button>
                                                {workflow.is_active ? (
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleStop(workflow.id)}
                                                        disabled={loading === workflow.id}
                                                    >
                                                        {loading === workflow.id ? '处理中...' : '停止'}
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="default"
                                                        size="sm"
                                                        onClick={() => handleStart(workflow.id)}
                                                        disabled={loading === workflow.id}
                                                    >
                                                        {loading === workflow.id ? '处理中...' : '启动'}
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Workflows.layout = {
    breadcrumbs: [
        {
            title: '工作流管理',
            href: '/admin/workflows',
        },
    ],
};
