import { ActivityTable, type ActivityRow } from '@/components/activity-log';
import type { Page, TableState } from '@/components/data-table';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';

type Option = { value: string; label: string };

/** Settings → Activity log: every sign-in, document view and change in this business. */
export default function ActivityLog({ entries, table, types, who }: { entries: Page<ActivityRow>; table: TableState; types: Option[]; who: Option[] }) {
    return (
        <AppLayout title="Activity log">
            <PageHeader
                title="Activity log"
                back={{ href: '/app/settings', label: 'Settings' }}
                description="Who signed in, opened a document or changed a record, and when. It is kept automatically and cannot be edited, so you can show it to a Home Office compliance officer or use it to answer a data protection question."
            />
            <ActivityTable
                url="/app/activity"
                entries={entries}
                table={table}
                linkEmployees
                filters={[
                    { name: 'type', label: 'What', options: types, allLabel: 'Everything' },
                    { name: 'who', label: 'Who', options: who, allLabel: 'Everyone' },
                ]}
            />
        </AppLayout>
    );
}
