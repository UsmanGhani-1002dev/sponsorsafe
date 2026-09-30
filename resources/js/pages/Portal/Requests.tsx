import { RequestList, type MyRequest } from '@/components/portal';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import PortalLayout from '@/layouts/portal-layout';
import { Inbox } from 'lucide-react';

export default function MyRequests({ rows }: { rows: MyRequest[] }) {
    return (
        <PortalLayout title="My requests">
            <h1 className="mb-1 text-[26px] font-semibold">My requests</h1>
            <p className="mb-5 text-[15px] text-ink-2">Everything you have sent to HR, and anything HR is waiting for from you.</p>
            <Card className="overflow-hidden">{rows.length ? <RequestList rows={rows} /> : <EmptyState icon={Inbox} title="No requests yet" />}</Card>
        </PortalLayout>
    );
}
