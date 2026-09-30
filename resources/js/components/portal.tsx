import { Badge, type Tone } from '@/components/ui/badge';

/** A request as the employee sees it (PortalController::requestRow / actionRow). */
export interface MyRequest {
    id: string;
    kind: string;
    summary: string;
    sent: string;
    status: { text: string; tone: Tone };
    hrNote: string | null;
}

export function RequestList({ rows }: { rows: MyRequest[] }) {
    return (
        <ul className="flex flex-col">
            {rows.map((r) => (
                <li key={r.id} className="flex flex-col gap-1 border-t border-line px-4 py-3.5 first:border-t-0 sm:px-5">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="font-semibold">{r.summary}</p>
                            <p className="text-[13px] text-muted">
                                {r.kind} · sent {r.sent}
                            </p>
                        </div>
                        <Badge tone={r.status.tone} className="shrink-0">
                            {r.status.text}
                        </Badge>
                    </div>
                    {r.hrNote && <p className="text-sm text-ink-2">HR: {r.hrNote}</p>}
                </li>
            ))}
        </ul>
    );
}
