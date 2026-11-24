import { DataTable, type Column, type Filter, type Page, type TableState } from '@/components/data-table';
import { EmptyState } from '@/components/ui/empty-state';
import { Link } from '@inertiajs/react';
import { History } from 'lucide-react';

export interface ActivityRow {
    id: number;
    when: string;
    who: { name: string; role: string };
    what: string;
    employee: { id: number | null; name: string } | null;
    business: string | null;
    ip: string | null;
}

/** The audit log as a table: Settings → Activity log, and the super admin's Activity log (with a Business column). */
export function ActivityTable({
    url,
    entries,
    table,
    filters,
    showBusiness = false,
    linkEmployees = false,
}: {
    url: string;
    entries: Page<ActivityRow>;
    table: TableState;
    filters: Filter[];
    showBusiness?: boolean;
    linkEmployees?: boolean;
}) {
    const filtered = table.q !== '' || Object.values(table.filters).some((v) => v !== null) || !!table.from || !!table.to;

    const columns: Column<ActivityRow>[] = [
        { key: 'when', label: 'When', sortable: true, className: 'whitespace-nowrap', render: (r) => r.when },
        ...(showBusiness ? [{ key: 'business', label: 'Business', render: (r: ActivityRow) => r.business ?? <span className="text-muted">—</span> }] : []),
        {
            key: 'who',
            label: 'Who',
            render: (r) => (
                <div className="flex min-w-[140px] flex-col">
                    <span className="font-medium">{r.who.name}</span>
                    {r.who.role && <span className="text-[13px] text-muted">{r.who.role}</span>}
                </div>
            ),
        },
        {
            key: 'what',
            label: 'What',
            render: (r) => (
                <div className="flex min-w-[240px] flex-col">
                    <span>{r.what}</span>
                    {r.employee && (
                        <span className="text-[13px] text-muted">
                            Employee:{' '}
                            {linkEmployees && r.employee.id ? (
                                <Link href={`/app/employees/${r.employee.id}`} prefetch className="font-medium text-ink-2 hover:text-accent hover:underline">
                                    {r.employee.name}
                                </Link>
                            ) : (
                                r.employee.name
                            )}
                        </span>
                    )}
                </div>
            ),
        },
        { key: 'ip', label: 'From', className: 'whitespace-nowrap text-[13px] text-muted', render: (r) => r.ip ?? 'Automatic' },
    ];

    return (
        <DataTable
            url={url}
            only={['entries', 'table']}
            page={entries}
            state={table}
            columns={columns}
            filters={filters}
            dateRange
            searchLabel={showBusiness ? 'Search by person or business' : 'Search by person'}
            empty={
                <EmptyState icon={History} title={filtered ? 'Nothing matches' : 'No activity yet'}>
                    {filtered ? 'Try a different search, filter or dates.' : 'Sign-ins, document views and every change appear here.'}
                </EmptyState>
            }
        />
    );
}
