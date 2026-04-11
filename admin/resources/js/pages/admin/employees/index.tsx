import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Employee {
    id: number;
    name: string;
    email: string;
    status: string;
    last_login_at: string | null;
    created_at: string;
}

interface PageProps {
    employees: {
        data: Employee[];
        links: Record<string, string>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

export default function EmployeesIndex() {
    const { employees, filters } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const handleSearch = () => {
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (status && status !== 'all') params.status = status;
        router.get('/admin/employees', params, { preserveState: true, preserveScroll: true });
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        router.get('/admin/employees', {}, { preserveState: true, preserveScroll: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('确定要禁用该员工吗？')) {
            router.delete(`/admin/employees/${id}`);
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString('zh-CN');
    };

    return (
        <>
            <Head title="员工管理" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">员工管理</h1>
                    <Button onClick={() => router.visit('/admin/employees/create')}>
                        新增员工
                    </Button>
                </div>

                {/* 搜索和过滤 */}
                <div className="flex gap-4 items-center">
                    <Input
                        placeholder="搜索姓名或邮箱..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="全部状态" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">全部状态</SelectItem>
                            <SelectItem value="active">正常</SelectItem>
                            <SelectItem value="inactive">禁用</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button onClick={handleSearch} variant="secondary">
                        搜索
                    </Button>
                    <Button onClick={handleReset} variant="outline">
                        重置
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 overflow-hidden">
                    <table className="w-full">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left text-sm font-medium">ID</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">姓名</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">邮箱</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">状态</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">最后登录</th>
                                <th className="px-4 py-3 text-left text-sm font-medium">创建时间</th>
                                <th className="px-4 py-3 text-right text-sm font-medium">操作</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {employees.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                                        暂无员工数据
                                    </td>
                                </tr>
                            ) : (
                                employees.data.map((employee) => (
                                    <tr key={employee.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono text-sm">{employee.id}</td>
                                        <td className="px-4 py-3 font-medium">{employee.name}</td>
                                        <td className="px-4 py-3 text-sm">{employee.email}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={employee.status === 'active' ? 'default' : 'secondary'}>
                                                {employee.status === 'active' ? '正常' : '禁用'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-sm">{formatDate(employee.last_login_at)}</td>
                                        <td className="px-4 py-3 text-sm">{formatDate(employee.created_at)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => router.visit(`/admin/employees/${employee.id}/edit`)}
                                                >
                                                    编辑
                                                </Button>
                                                {employee.status === 'active' && (
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleDelete(employee.id)}
                                                    >
                                                        禁用
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

                {/* 分页信息 */}
                {employees.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <div>
                            共 {employees.total} 条记录，第 {employees.current_page}/{employees.last_page} 页
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={employees.current_page === 1}
                                onClick={() => {
                                    const params: Record<string, string> = { page: String(employees.current_page - 1) };
                                    if (search) params.search = search;
                                    if (status && status !== 'all') params.status = status;
                                    router.get('/admin/employees', params);
                                }}
                            >
                                上一页
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={employees.current_page === employees.last_page}
                                onClick={() => {
                                    const params: Record<string, string> = { page: String(employees.current_page + 1) };
                                    if (search) params.search = search;
                                    if (status && status !== 'all') params.status = status;
                                    router.get('/admin/employees', params);
                                }}
                            >
                                下一页
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        {
            title: '员工管理',
            href: '/admin/employees',
        },
    ],
};
