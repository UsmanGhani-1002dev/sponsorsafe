import { ActivityTable, type ActivityRow } from '@/components/activity-log';
import type { Page, TableState } from '@/components/data-table';
import OpsLayout from '@/layouts/ops-layout';

type Option = { value: string; label: string };

/** Super admin Activity log: the audit log across every business and the super admin area. */
export default function Activity({ base, entries, table, types, businesses }: { base: string; entries: Page<ActivityRow>; table: TableState; types: Option[]; businesses: Option[] }) {
    return (
        <OpsLayout title="Activity log" base={base}>
            <h1 className="mb-1 text-[26px] font-semibold">Activity log</h1>
            <p className="mb-5 text-[15px] text-ink-2">Every sign-in, document view, change and billing event, across all businesses and this area. Newest first.</p>
            <ActivityTable
                url={`${base}/activity`}
                entries={entries}
                table={table}
                showBusiness
                filters={[
                    { name: 'business', label: 'Business', options: businesses, allLabel: 'All businesses' },
                    { name: 'type', label: 'What', options: types, allLabel: 'Everything' },
                ]}
            />
        </OpsLayout>
    );
}
