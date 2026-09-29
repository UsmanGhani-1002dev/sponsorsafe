import { DataTable, type Column, type Page, type TableState } from '@/components/data-table';
import { Alert } from '@/components/ui/alert';
import { Badge, type Tone } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';

interface Row {
    id: number;
    name: string;
    site: string | null;
    jobTitle: string;
    status: string;
    expiry: { text: string; tone: Tone };
    portal: 'none' | 'invited' | 'active';
    documents: { have: number; need: number };
}

interface Props {
    employees: Page<Row>;
    table: TableState;
    bases: { value: string; label: string }[];
    plan: { used: number; limit: number; reached: boolean };
}

const portal: Record<Row['portal'], { text: string; tone: Tone }> = {
    active: { text: 'Active', tone: 'green' },
    invited: { text: 'Invited', tone: 'blue' },
    none: { text: 'No access', tone: 'grey' },
};

export default function EmployeesIndex({ employees, table, bases, plan }: Props) {
    const filtered = table.q !== '' || Object.values(table.filters).some((v) => v !== null);

    const columns: Column<Row>[] = [
        {
            key: 'name',
            label: 'Employee',
            sortable: true,
            render: (e) => (
                <div className="flex flex-col">
                    <Link href={`/app/employees/${e.id}`} prefetch className="text-[15px] font-semibold text-ink hover:text-accent hover:underline">
                        {e.name}
                    </Link>
                    <span className="text-[13px] text-muted">{e.site ?? 'No site'}</span>
                </div>
            ),
        },
        { key: 'job', label: 'Job title', sortable: true, render: (e) => e.jobTitle },
        { key: 'status', label: 'Right to work', render: (e) => e.status },
        { key: 'expiry', label: 'Expiry', sortable: true, render: (e) => <Badge tone={e.expiry.tone}>{e.expiry.text}</Badge> },
        {
            key: 'documents',
            label: 'Documents',
            render: (e) => (
                <Badge tone={e.documents.have === e.documents.need ? 'green' : 'amber'}>
                    {e.documents.have} of {e.documents.need}
                </Badge>
            ),
        },
        { key: 'portal', label: 'Portal', render: (e) => <Badge tone={portal[e.portal].tone}>{portal[e.portal].text}</Badge> },
    ];

    return (
        <AppLayout title="Employees">
            <PageHeader
                title="Employees"
                description={`${plan.used} of ${plan.limit} employees on your plan.`}
                actions={
                    plan.reached ? undefined : (
                        <Link href="/app/employees/create" prefetch className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-accent-fill px-4 text-[15px] font-semibold text-white hover:bg-accent-fill-hover">
                            <Plus size={18} aria-hidden /> Add employee
                        </Link>
                    )
                }
            />
            {plan.reached && (
                <div className="mb-5">
                    <Alert tone="info">Your plan covers up to {plan.limit} employees. Contact us to add more.</Alert>
                </div>
            )}
            <DataTable
                url="/app/employees"
                only={['employees', 'table']}
                page={employees}
                state={table}
                columns={columns}
                searchLabel="Search by name, job title or email"
                filters={[
                    {
                        name: 'status',
                        label: 'Show',
                        fallback: 'current',
                        options: [
                            { value: 'current', label: 'Current employees' },
                            { value: 'left', label: 'Leavers' },
                            { value: 'all', label: 'Everyone' },
                        ],
                    },
                    { name: 'basis', label: 'Right-to-work basis', allLabel: 'Any basis', options: bases },
                ]}
                empty={
                    filtered ? (
                        <EmptyState icon={Users} title="No employees match">
                            Try a different search or filter.
                        </EmptyState>
                    ) : (
                        <EmptyState
                            icon={Users}
                            title="No employees yet"
                            action={
                                <Link href="/app/employees/create" className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-accent-fill px-4 text-[15px] font-semibold text-white hover:bg-accent-fill-hover">
                                    <Plus size={18} aria-hidden /> Add the first one
                                </Link>
                            }
                        >
                            Add each person you employ. The form asks only for what their right-to-work basis needs.
                        </EmptyState>
                    )
                }
            />
        </AppLayout>
    );
}
