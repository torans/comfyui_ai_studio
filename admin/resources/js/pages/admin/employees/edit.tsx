import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Employee {
    id: number;
    name: string;
    email: string;
    status: string;
    last_login_at: string | null;
    created_at: string;
}

interface PageProps {
    employee: Employee;
}

export default function EmployeesEdit({ employee }: PageProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: employee.name,
        email: employee.email,
        password: '',
        password_confirmation: '',
        status: employee.status,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/employees/${employee.id}`);
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString('zh-CN');
    };

    return (
        <>
            <Head title="编辑员工" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">编辑员工</h1>
                    <Button variant="outline" onClick={() => window.history.back()}>
                        返回
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* 员工信息表单 */}
                    <Card>
                        <CardHeader>
                            <CardTitle>员工信息</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">姓名 *</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="请输入员工姓名"
                                            required
                                        />
                                        {errors.name && (
                                            <p className="text-sm text-destructive">{errors.name}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">邮箱 *</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="请输入邮箱地址"
                                            required
                                        />
                                        {errors.email && (
                                            <p className="text-sm text-destructive">{errors.email}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">新密码（留空则不修改）</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="请输入新密码（至少6位）"
                                            minLength={6}
                                        />
                                        {errors.password && (
                                            <p className="text-sm text-destructive">{errors.password}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">确认新密码</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            placeholder="请再次输入新密码"
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">状态 *</Label>
                                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="选择状态" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">正常</SelectItem>
                                                <SelectItem value="inactive">禁用</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.status && (
                                            <p className="text-sm text-destructive">{errors.status}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex justify-end gap-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => window.history.back()}
                                    >
                                        取消
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? '保存中...' : '保存'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* 员工统计信息 */}
                    <Card>
                        <CardHeader>
                            <CardTitle>账户信息</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">员工 ID</span>
                                    <span className="font-mono">{employee.id}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">最后登录时间</span>
                                    <span>{formatDate(employee.last_login_at)}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">创建时间</span>
                                    <span>{formatDate(employee.created_at)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

EmployeesEdit.layout = {
    breadcrumbs: [
        {
            title: '员工管理',
            href: '/admin/employees',
        },
        {
            title: '编辑员工',
            href: '/admin/employees/edit',
        },
    ],
};
