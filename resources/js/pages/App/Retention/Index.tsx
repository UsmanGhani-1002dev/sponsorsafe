import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Due {
    id: number;
    name: string;
    left: string;
    step: 'records' | 'all';
    deleteAfter: string | null;
    rtwDeleteAfter: string | null;
    counts: { documents: number; absences: number; requests: number; tasks: number };
}

const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`;

export default function RetentionReview({ due, rules }: { due: Due[]; rules: { records: number; rtw: number } }) {
    const [confirming, setConfirming] = useState<Due | null>(null);
    const [processing, setProcessing] = useState(false);

    const purge = () => {
        if (!confirming) return;
        setProcessing(true);
        router.delete(`/app/retention/${confirming.id}`, { preserveScroll: true, onFinish: () => (setProcessing(false), setConfirming(null)) });
    };

    return (
        <AppLayout title="Records due for deletion">
            <PageHeader
                title="Records due for deletion"
                back={{ href: '/app/settings', label: 'Settings' }}
                description={`Leavers' records are kept for ${plural(rules.records, 'year')} after employment ends, and right-to-work evidence for ${plural(rules.rtw, 'year')}. Review this list each month and delete what is due. Deletion is permanent.`}
            />

            {due.length === 0 ? (
                <Card>
                    <EmptyState icon={ShieldCheck} title="Nothing is due for deletion">
                        Leavers appear here once their retention period ends.
                    </EmptyState>
                </Card>
            ) : (
                <ul className="flex flex-col gap-3">
                    {due.map((d) => (
                        <li key={d.id}>
                            <Card className="flex flex-wrap items-center justify-between gap-4 p-5">
                                <div className="min-w-0">
                                    <p className="text-[15px] font-semibold">{d.name}</p>
                                    <p className="text-[13px] text-muted">Left {d.left}</p>
                                    <p className="mt-1 text-sm text-ink-2">
                                        {d.step === 'all'
                                            ? `Everything is due for deletion (right-to-work evidence kept until ${d.rtwDeleteAfter}): the record, ${plural(d.counts.documents, 'document')}, change history and their portal login.`
                                            : `Due since ${d.deleteAfter}: ${plural(d.counts.documents, 'document')}, ${plural(d.counts.absences, 'absence')}, ${plural(d.counts.requests, 'request')} and ${plural(d.counts.tasks, 'Home Office task')}. Right-to-work evidence stays until ${d.rtwDeleteAfter}.`}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={d.step === 'all' ? 'red' : 'amber'}>{d.step === 'all' ? 'Delete everything' : 'Delete records'}</Badge>
                                    <Button variant="danger" onClick={() => setConfirming(d)}>
                                        <Trash2 size={16} aria-hidden /> Delete permanently
                                    </Button>
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmDialog open={confirming !== null} title={`Permanently delete ${confirming?.name ?? ''}'s records?`} confirmLabel="Delete permanently" danger processing={processing} onConfirm={purge} onClose={() => setConfirming(null)}>
                {confirming?.step === 'all'
                    ? 'Their employee record, all documents (files are erased), change history and portal login will be deleted. This cannot be undone.'
                    : 'Their documents (except right-to-work evidence), absences, requests and Home Office tasks will be deleted. This cannot be undone.'}
            </ConfirmDialog>
        </AppLayout>
    );
}
