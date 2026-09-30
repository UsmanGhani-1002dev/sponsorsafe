import { Badge, type Tone } from '@/components/ui/badge';
import { Link } from '@inertiajs/react';

/** One absence as the server sends it (AbsenceController::row). */
export interface AbsenceRow {
    id: number;
    employeeId: number;
    employee: string;
    type: string;
    dates: string;
    days: number;
    pay: string;
    reason: string | null;
    homeOffice: { text: string; tone: Tone };
    fitNoteMissing: boolean;
    taskId: number | null;
}

/** Home Office status for an absence (follows its report task), plus a "Fit note missing" flag. */
export function AbsenceBadges({ row }: { row: AbsenceRow }) {
    return (
        <div className="flex flex-col items-start gap-1.5">
            <div className="flex flex-wrap gap-1.5">
                <Badge tone={row.homeOffice.tone}>{row.homeOffice.text}</Badge>
                {row.fitNoteMissing && <Badge tone="amber">Fit note missing</Badge>}
            </div>
            {row.taskId && (
                <Link href={`/app/reports?task=${row.taskId}`} className="inline-flex min-h-8 items-center text-[13px] font-semibold text-accent hover:underline">
                    Tick as reported
                </Link>
            )}
        </div>
    );
}
