import { Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function LicenseExpired() {
    return (
        <>
            <Head title="系统授权已过期" />

            <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
                <div className="max-w-md w-full space-y-8">
                    <div className="text-center">
                        <AlertCircle className="mx-auto h-16 w-16 text-red-500" />
                        <h2 className="mt-6 text-3xl font-extrabold text-gray-900">
                            系统授权已过期
                        </h2>
                        <p className="mt-2 text-sm text-gray-600">
                            请联系供应商获取新的授权
                        </p>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>如何恢复使用</CardTitle>
                            <CardDescription>
                                在 .env 文件中修改到期时间
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="text-sm space-y-2">
                                <p>修改 <code className="bg-gray-100 px-1 rounded">.env</code> 文件中的到期时间：</p>
                                <pre className="bg-gray-100 p-3 rounded text-xs overflow-x-auto">
APP_LICENSE_EXPIRES="2026-12-31 00:00:00"
                                </pre>
                                <p className="text-muted-foreground text-xs">
                                    修改后重启服务生效
                                </p>
                            </div>

                            <Button 
                                onClick={() => window.location.reload()}
                                className="w-full"
                            >
                                刷新页面
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
