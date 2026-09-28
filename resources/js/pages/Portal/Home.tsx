import { Card } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';

export default function Home({ business }: { business: string }) {
    const { auth } = usePage<SharedProps>().props;
    const first = auth.user?.name.split(' ')[0];
    return (
        <PortalLayout title="Home">
            <h1 className="text-[26px] font-semibold">Hello, {first}</h1>
            <p className="mt-1 text-ink-2">{business}</p>
            <Card className="mt-6 p-6">
                <h2 className="text-lg font-semibold">Your employee portal</h2>
                <p className="mt-1 text-[15px] text-ink-2">Soon you will upload documents, request leave, report sickness and update your details here.</p>
            </Card>
        </PortalLayout>
    );
}
