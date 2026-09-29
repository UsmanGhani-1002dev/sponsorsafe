import { AbsenceBadges, type AbsenceRow } from '@/components/absence';
import { DataTable, tableQuery, type Column, type Page, type TableState } from '@/components/data-table';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { CalendarX2, Download, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    absences: Page<AbsenceRow>;
    table: TableState;
    employees: { value: string; label: string }[];
    types: { value: string; label: string }[];
}

const linkButton = 'inline-flex min-h-11 items-center gap-2 rounded-lg px-4 text-[15px] font-semibold';

export default function AbsenceIndex({ absences, table, employees, types }: Props) {
    const [removing, setRemoving] = useState<AbsenceRow | null>(null);
    const filtered = table.q !== '' || !!table.from || !!table.to || Object.values(table.filters).some((v) => v !== null);
    const query = tableQuery(table);

    const columns: Column<AbsenceRow>[] = [
        {
            key: 'employee',
            label: 'Employee',
            sortable: true,
            render: (a) => (
                <Link href={`/app/employees/${a.employeeId}?tab=absence`} prefetch className="font-semibold text-ink hover:text-accent hover:underline">
                    {a.employee}
                </Link>
            ),
        },
        { key: 'type', label: 'Type', render: (a) => a.type },
        { key: 'start', label: 'Dates', sortable: true, className: 'whitespace-nowrap', render: (a) => a.dates },
        { key: 'days', label: 'Days', sortable: true, className: 'text-right', render: (a) => <span className="font-mono">{a.days}</span> },
        { key: 'pay', label: 'Pay', render: (a) => a.pay },
        { key: 'reason', label: 'Reason', render: (a) => <span className="text-ink-2">{a.reason ?? '—'}</span> },
        { key: 'ho', label: 'Home Office', render: (a) => <AbsenceBadges row={a} /> },
        {
            key: 'actions',
            label: 'Actions',
            render: (a) => (
                <button type="button" onClick={() => setRemoving(a)} aria-label={`Remove ${a.type.toLowerCase()} for ${a.employee}, ${a.dates}`} className="inline-flex size-10 items-center justify-center rounded-lg text-muted hover:bg-canvas hover:text-red-700">
                    <Trash2 size={17} aria-hidden />
                </button>
            ),
        },
    ];

    return (
        <AppLayout title="Absence log">
            <PageHeader
                title="Absence log"
                description="The compliance record of every absence. Days are working days (Monday to Friday, excluding bank holidays)."
                actions={
                    <Link href="/app/absence/create" prefetch className={`${linkButton} bg-accent-fill text-white hover:bg-accent-fill-hover`}>
                        <Plus size={18} aria-hidden /> Record absence
                    </Link>
                }
            />
            <DataTable
                url="/app/absence"
                only={['absences', 'table']}
                page={absences}
                state={table}
                columns={columns}
                searchLabel="Search by employee or reason"
                dateRange
                filters={[
                    { name: 'employee', label: 'Employee', allLabel: 'Everyone', options: employees },
                    { name: 'type', label: 'Type', allLabel: 'All types', options: types },
                ]}
                toolbar={
                    <>
                        <a href={`/app/absence/export.csv?${query}`} className={`${linkButton} border border-line-strong bg-surface text-sm text-ink-2 hover:bg-canvas`}>
                            <Download size={16} aria-hidden /> CSV
                        </a>
                        <a href={`/app/absence/export.pdf?${query}`} className={`${linkButton} border border-line-strong bg-surface text-sm text-ink-2 hover:bg-canvas`}>
                            <Download size={16} aria-hidden /> PDF
                        </a>
                    </>
                }
                empty={
                    <EmptyState icon={CalendarX2} title={filtered ? 'No absences match' : 'No absences recorded yet'}>
                        {filtered ? 'Try different filters or dates.' : 'Record leave, sickness and unauthorised absence here. The Home Office check runs as you type.'}
                    </EmptyState>
                }
            />

            <ConfirmDialog
                open={removing !== null}
                title="Remove this absence?"
                confirmLabel="Remove"
                danger
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && router.delete(`/app/absence/${removing.id}`, { preserveScroll: true, preserveState: true, onFinish: () => setRemoving(null) })}
            >
                {removing && `${removing.employee}: ${removing.type.toLowerCase()}, ${removing.dates}. Only remove entries made by mistake; the removal is recorded in the audit log.`}
            </ConfirmDialog>
        </AppLayout>
    );
}
