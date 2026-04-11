import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="外观设置" />

            <h1 className="sr-only">外观设置</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="外观设置"
                    description="更新您账号的外观设置"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: '外观设置',
            href: editAppearance(),
        },
    ],
};
