import { Badge, type Tone } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { CheckCircle2, ChevronDown } from 'lucide-react';
import { useState } from 'react';

export interface Compliance {
    score: number;
    tone: 'red' | 'amber' | 'green';
    toFix: number;
    employees: number;
    fixes: { key: string; name: string; label: string; detail: string; badge: { text: string; tone: Tone }; missing: boolean; href: string }[];
    people: { id: number; name: string; text: string; tone: Tone }[];
}

const ring = { red: 'text-red-600', amber: 'text-amber-500', green: 'text-green-600' } as const;

/** Score ring: the share of compliance items done across every current employee. */
function Ring({ score, tone }: { score: number; tone: Compliance['tone'] }) {
    const r = 42;
    const c = 2 * Math.PI * r;
    return (
        <div className="relative size-28 shrink-0">
            <svg viewBox="0 0 100 100" className="size-28 -rotate-90" aria-hidden>
                <circle cx="50" cy="50" r={r} fill="none" strokeWidth="10" className="stroke-current text-subtle" />
                <circle cx="50" cy="50" r={r} fill="none" strokeWidth="10" strokeLinecap="round" strokeDasharray={c} strokeDashoffset={c * (1 - score / 100)} className={cn('stroke-current', ring[tone])} />
            </svg>
            <span className="absolute inset-0 flex items-center justify-center font-mono text-[26px] font-semibold">{score}%</span>
        </div>
    );
}

/** Dashboard "Compliance": one answer to "am I ready if the Home Office visits tomorrow?", and what to fix next. */
export function ComplianceScoreCard({ compliance }: { compliance: Compliance }) {
    const [showPeople, setShowPeople] = useState(false);
    const { score, tone, toFix, employees, fixes, people } = compliance;
    const headline = toFix === 0 ? 'Everything is in order' : `${toFix} ${toFix === 1 ? 'thing' : 'things'} to fix`;

    return (
        <section aria-labelledby="compliance-title" className="mb-6">
            <Card className="overflow-hidden">
                <div className="grid gap-0 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                    <div className="flex items-center gap-5 border-b border-line p-5 lg:border-r lg:border-b-0">
                        <Ring score={score} tone={tone} />
                        <div className="min-w-0">
                            <h2 id="compliance-title" className="text-lg font-semibold">
                                Compliance
                            </h2>
                            <p className={cn('text-[15px] font-semibold', tone === 'red' ? 'text-red-700 dark:text-red-300' : tone === 'amber' ? 'text-amber-700 dark:text-amber-300' : 'text-green-700 dark:text-green-300')}>
                                {headline}
                            </p>
                            <p className="mt-1 text-[13px] text-muted">
                                Across {employees} current {employees === 1 ? 'employee' : 'employees'}: right to work, documents, pay and Home Office reports.
                            </p>
                        </div>
                    </div>

                    <div className="min-w-0">
                        {fixes.length === 0 ? (
                            <div className="flex h-full items-center gap-3 px-5 py-6 text-[15px] text-ink-2">
                                <CheckCircle2 size={22} className="shrink-0 text-green-600" aria-hidden />
                                Every employee's records are complete. Keep uploading payslips and recording changes as they happen.
                            </div>
                        ) : (
                            <>
                                <h3 className="px-5 pt-4 pb-1 text-[13px] font-semibold tracking-wide text-muted uppercase">Fix next</h3>
                                <ul>
                                    {fixes.map((f) => (
                                        <li key={f.key} className="border-t border-line first:border-t-0">
                                            <Link href={f.href} prefetch className="flex items-start gap-3 px-5 py-3 hover:bg-canvas">
                                                <span aria-hidden className={cn('mt-1.5 size-2.5 shrink-0 rounded-full', f.missing ? 'bg-red-600' : 'bg-amber-500')} />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-[15px]">
                                                        <strong>{f.name}</strong> · {f.label}
                                                    </span>
                                                    <span className="block text-[13px] text-muted">{f.detail}</span>
                                                </span>
                                                <Badge tone={f.badge.tone} className="shrink-0">
                                                    {f.badge.text}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                                {toFix > fixes.length && <p className="border-t border-line px-5 py-2.5 text-[13px] text-muted">And {toFix - fixes.length} more. See each employee below.</p>}
                            </>
                        )}
                    </div>
                </div>

                <div className="border-t border-line">
                    <button
                        type="button"
                        onClick={() => setShowPeople((v) => !v)}
                        aria-expanded={showPeople}
                        className="flex min-h-11 w-full items-center justify-between px-5 text-left text-sm font-semibold text-ink-2 hover:bg-canvas"
                    >
                        Each employee
                        <ChevronDown size={18} aria-hidden className={cn('transition-transform', showPeople && 'rotate-180')} />
                    </button>
                    {showPeople && (
                        <ul className="grid gap-x-6 border-t border-line px-5 py-2 sm:grid-cols-2 xl:grid-cols-3">
                            {people.map((p) => (
                                <li key={p.id}>
                                    <Link href={`/app/employees/${p.id}?tab=check`} prefetch className="flex min-h-11 items-center justify-between gap-3 hover:text-accent">
                                        <span className="truncate font-medium">{p.name}</span>
                                        <Badge tone={p.tone}>{p.text}</Badge>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </Card>
        </section>
    );
}
