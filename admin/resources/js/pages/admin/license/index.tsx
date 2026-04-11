import { Head } from '@inertiajs/react';
import { CheckCircle, Clock, AlertCircle, Key, Shield } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface LicenseInfo {
    is_licensed: boolean;
    has_permanent_license: boolean;
    has_valid_license_key: boolean;
    is_in_trial: boolean;
    remaining_days: number;
    install_date: string;
    trial_expires: string;
}

interface Props {
    license_info: LicenseInfo;
}

export default function LicenseIndex({ license_info }: Props) {
    const getStatusBadge = () => {
        if (license_info.has_permanent_license) {
            return <Badge className="bg-green-500"><CheckCircle className="w-3 h-3 mr-1" />永久授权</Badge>;
        }
        if (license_info.has_valid_license_key) {
            return <Badge className="bg-blue-500"><Key className="w-3 h-3 mr-1" />已授权</Badge>;
        }
        if (license_info.is_in_trial) {
            return <Badge className="bg-yellow-500"><Clock className="w-3 h-3 mr-1" />试用期</Badge>;
        }
        return <Badge variant="destructive"><AlertCircle className="w-3 h-3 mr-1" />未授权</Badge>;
    };

    const getStatusColor = () => {
        if (license_info.is_licensed) {
            return 'text-green-600';
        }
        return 'text-red-600';
    };

    return (
        <>
            <Head title="授权管理" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">授权管理</h1>
                    {getStatusBadge()}
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* 授权状态卡片 */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                授权状态
                            </CardTitle>
                            <CardDescription>
                                当前系统的授权状态
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">授权状态</span>
                                    <span className={`font-medium ${getStatusColor()}`}>
                                        {license_info.is_licensed ? '已授权' : '未授权'}
                                    </span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">永久授权</span>
                                    <span>{license_info.has_permanent_license ? '是' : '否'}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">授权码</span>
                                    <span>{license_info.has_valid_license_key ? '有效' : '无'}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">试用期</span>
                                    <span>{license_info.is_in_trial ? '是' : '否'}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">剩余天数</span>
                                    <span className={license_info.remaining_days <= 7 ? 'text-red-600 font-medium' : ''}>
                                        {license_info.remaining_days === 2147483647 
                                            ? '永久' 
                                            : `${license_info.remaining_days} 天`}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* 日期信息卡片 */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                日期信息
                            </CardTitle>
                            <CardDescription>
                                系统安装和试用期信息
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">安装日期</span>
                                    <span>{license_info.install_date}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">试用期限</span>
                                    <span>30 天</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-muted-foreground">到期日期</span>
                                    <span>{license_info.trial_expires}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* 提示信息 */}
                {!license_info.is_licensed && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>系统未授权</AlertTitle>
                        <AlertDescription>
                            系统试用期已结束，请联系供应商获取授权。API接口已暂停服务。
                        </AlertDescription>
                    </Alert>
                )}

                {license_info.is_in_trial && license_info.remaining_days <= 7 && (
                    <Alert>
                        <Clock className="h-4 w-4" />
                        <AlertTitle>试用期即将结束</AlertTitle>
                        <AlertDescription>
                            系统试用期还剩 {license_info.remaining_days} 天，请尽快联系供应商获取授权。
                        </AlertDescription>
                    </Alert>
                )}

                {/* 授权方式说明 */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5" />
                            授权方式
                        </CardTitle>
                        <CardDescription>
                            如何获取和设置系统授权
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-2">
                            <h4 className="font-medium">方式一：设置永久授权</h4>
                            <p className="text-sm text-muted-foreground">
                                在 <code className="bg-gray-100 px-1 rounded">.env</code> 文件中添加：
                            </p>
                            <pre className="bg-gray-100 p-3 rounded text-sm overflow-x-auto">
APP_LICENSE_PERMANENT=true
                            </pre>
                        </div>

                        <div className="space-y-2">
                            <h4 className="font-medium">方式二：使用授权码</h4>
                            <p className="text-sm text-muted-foreground">
                                在 <code className="bg-gray-100 px-1 rounded">.env</code> 文件中添加：
                            </p>
                            <pre className="bg-gray-100 p-3 rounded text-sm overflow-x-auto">
APP_LICENSE_KEY=BKM-XXXX-XXXX-XXXX-XXXX
                            </pre>
                            <p className="text-xs text-muted-foreground">
                                联系供应商获取有效的授权码
                            </p>
                        </div>

                        <div className="space-y-2">
                            <h4 className="font-medium">方式三：延长试用期</h4>
                            <p className="text-sm text-muted-foreground">
                                在 <code className="bg-gray-100 px-1 rounded">.env</code> 文件中修改安装日期：
                            </p>
                            <pre className="bg-gray-100 p-3 rounded text-sm overflow-x-auto">
APP_INSTALL_DATE=2026-04-11 00:00:00
                            </pre>
                            <p className="text-xs text-muted-foreground">
                                修改后重启服务生效
                            </p>
                        </div>

                        <Button 
                            onClick={() => window.location.reload()}
                            className="w-full"
                        >
                            刷新授权状态
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LicenseIndex.layout = {
    breadcrumbs: [
        {
            title: '授权管理',
            href: '/admin/license',
        },
    ],
};
