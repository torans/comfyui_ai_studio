import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

interface Stats {
    pending_jobs: number;
    running_jobs: number;
    failed_jobs: number;
}

interface RecentAsset {
    id: number;
    filename: string;
    type: string;
    created_at: string;
}

interface DashboardProps {
    stats: Stats;
    recent_assets: RecentAsset[];
}

export default function Dashboard({ stats, recent_assets }: DashboardProps) {
    return (
        <>
            <Head title="控制面板" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                        <div className="text-sm text-muted-foreground">待处理任务</div>
                        <div className="mt-2 text-3xl font-bold">{stats.pending_jobs}</div>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                        <div className="text-sm text-muted-foreground">运行中任务</div>
                        <div className="mt-2 text-3xl font-bold">{stats.running_jobs}</div>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                        <div className="text-sm text-muted-foreground">失败任务</div>
                        <div className="mt-2 text-3xl font-bold">{stats.failed_jobs}</div>
                    </div>
                </div>
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h3 className="text-lg font-semibold mb-4">最近生成资源</h3>
                    <div className="space-y-2">
                        {recent_assets.length === 0 ? (
                            <p className="text-muted-foreground">暂无最近生成资源</p>
                        ) : (
                            recent_assets.map((asset) => (
                                <div key={asset.id} className="flex items-center justify-between border-b border-sidebar-border/50 pb-2">
                                    <span className="font-mono text-sm">{asset.filename}</span>
                                    <span className="text-sm text-muted-foreground">{asset.type}</span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: '控制面板',
            href: dashboard(),
        },
    ],
};
