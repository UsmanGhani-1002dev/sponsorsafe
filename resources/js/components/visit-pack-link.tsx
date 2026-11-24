import { FileDown } from 'lucide-react';

/** "Prepare for a Home Office visit": downloads one PDF for the whole business (a normal link, not an Inertia visit). */
export function VisitPackLink() {
    return (
        <a
            href="/app/visit-pack"
            title="One PDF: sponsor details, key personnel, work sites, every employee's compliance status, pending reports, and each employee's compliance pack"
            className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-line-strong bg-surface px-4 text-[15px] font-semibold text-ink-2 hover:bg-canvas"
        >
            <FileDown size={18} aria-hidden /> Prepare for a Home Office visit
        </a>
    );
}
