import { Badge, type Tone } from '@/components/ui/badge';

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
}

/** Home Office status for an absence, plus a "Fit note missing" flag when it applies. */
export function AbsenceBadges({ row }: { row: AbsenceRow }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            <Badge tone={row.homeOffice.tone}>{row.homeOffice.text}</Badge>
            {row.fitNoteMissing && <Badge tone="amber">Fit note missing</Badge>}
        </div>
    );
}
