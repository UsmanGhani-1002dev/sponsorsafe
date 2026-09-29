import { AbsenceBadges, type AbsenceRow } from '@/components/absence';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Link, router } from '@inertiajs/react';
import { CalendarX2, Paperclip, Plus } from 'lucide-react';

/** Absence tab on the profile: unpaid days against the limit, annual leave left, and this person's absences. */
export function AbsenceTab({
    employeeId,
    left,
    absence,
}: {
    employeeId: number;
    left: boolean;
    absence: { year: string; unpaid: { used: number; limit: string }; annual: { allowance: string; taken: number; left: string }; rows: AbsenceRow[] };
}) {
    const attach = (id: number, file: File | undefined) => file && router.post(`/app/absence/${id}/fit-note`, { fit_note: file }, { forceFormData: true, preserveScroll: true, preserveState: true });
    const overLimit = absence.unpaid.used > Number(absence.unpaid.limit);

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-stretch gap-4">
                <Card className="min-w-[220px] flex-1 p-5">
                    <p className="text-sm text-muted">Unpaid and unauthorised days in {absence.year}</p>
                    <p className={`mt-1 font-mono text-2xl font-semibold ${overLimit ? 'text-red-700 dark:text-red-300' : ''}`}>
                        {absence.unpaid.used} <span className="text-base text-muted">of {absence.unpaid.limit}</span>
                    </p>
                </Card>
                <Card className="min-w-[220px] flex-1 p-5">
                    <p className="text-sm text-muted">Annual leave left in {absence.year}</p>
                    <p className="mt-1 font-mono text-2xl font-semibold">
                        {absence.annual.left} <span className="text-base text-muted">of {absence.annual.allowance} days</span>
                    </p>
                </Card>
                {!left && (
                    <div className="flex items-center">
                        <Link href={`/app/absence/create?employee=${employeeId}`} prefetch className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-accent-fill px-4 text-[15px] font-semibold text-white hover:bg-accent-fill-hover">
                            <Plus size={18} aria-hidden /> Record absence
                        </Link>
                    </div>
                )}
            </div>

            <Card className="overflow-hidden">
                {absence.rows.length === 0 ? (
                    <EmptyState icon={CalendarX2} title="No absences recorded" />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas text-[13px] font-semibold text-ink-2">
                                <tr>
                                    <th scope="col" className="px-5 py-3">Type</th>
                                    <th scope="col" className="px-5 py-3">Dates</th>
                                    <th scope="col" className="px-5 py-3 text-right">Days</th>
                                    <th scope="col" className="px-5 py-3">Pay</th>
                                    <th scope="col" className="px-5 py-3">Reason</th>
                                    <th scope="col" className="px-5 py-3">Home Office</th>
                                </tr>
                            </thead>
                            <tbody>
                                {absence.rows.map((a) => (
                                    <tr key={a.id} className="border-t border-line align-top">
                                        <td className="px-5 py-3.5 font-medium">{a.type}</td>
                                        <td className="px-5 py-3.5 whitespace-nowrap">{a.dates}</td>
                                        <td className="px-5 py-3.5 text-right font-mono">{a.days}</td>
                                        <td className="px-5 py-3.5">{a.pay}</td>
                                        <td className="px-5 py-3.5 text-ink-2">{a.reason ?? '—'}</td>
                                        <td className="px-5 py-3.5">
                                            <AbsenceBadges row={a} />
                                            {a.fitNoteMissing && (
                                                <label className="mt-1.5 inline-flex min-h-10 cursor-pointer items-center gap-1.5 rounded-md text-[13px] font-semibold text-accent hover:underline">
                                                    <Paperclip size={14} aria-hidden /> Add fit note
                                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="sr-only" onChange={(e) => attach(a.id, e.target.files?.[0])} />
                                                </label>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </div>
    );
}
