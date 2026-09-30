import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import type { Tone } from '@/components/ui/badge';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent } from 'react';

/** One Home Office task as the server sends it (ReportTaskController::row). */
export interface TaskRow {
    id: number;
    event: string;
    level: 'worker' | 'company';
    who: string;
    employeeId: number | null;
    source: string;
    trigger: string;
    triggerIso: string;
    deadline: string;
    badge: { text: string; tone: Tone };
    pending: boolean;
    done: string | null;
}

const opts = { preserveScroll: true, preserveState: true } as const;

export function reopenTask(id: number) {
    router.post(`/app/reports/${id}/reopen`, {}, opts);
}

/**
 * "Mark reported" (date and who are required; the SMS reference is optional) or "Not required"
 * (a reason is required). Native <dialog>: focus is trapped and Escape closes it.
 */
export function ReportDialog({ task, reporter, today, onClose }: { task: TaskRow | null; reporter: string; today: string; onClose: () => void }) {
    const ref = useRef<HTMLDialogElement>(null);
    const form = useForm({ reported_on: today, reported_by: reporter, notes: '' });

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (task && !dialog.open) {
            form.setData({ reported_on: today, reported_by: reporter, notes: '' });
            form.clearErrors();
            dialog.showModal();
        }
        if (!task && dialog.open) dialog.close();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [task]);

    const reported = (e: FormEvent) => {
        e.preventDefault();
        if (task) form.post(`/app/reports/${task.id}/reported`, { ...opts, onSuccess: onClose });
    };
    const notRequired = () => task && form.post(`/app/reports/${task.id}/not-required`, { ...opts, onSuccess: onClose });

    return (
        <dialog ref={ref} onClose={onClose} aria-labelledby="report-title" className="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-line bg-surface p-0 text-ink shadow-xl backdrop:bg-black/40">
            {task && (
                <form onSubmit={reported} noValidate className="flex flex-col gap-4 p-6">
                    <div>
                        <h2 id="report-title" className="text-lg font-semibold">
                            Mark reported
                        </h2>
                        <p className="mt-1 text-sm text-ink-2">
                            {task.event} · {task.who} · deadline {task.deadline}
                        </p>
                    </div>
                    <p className="text-sm text-ink-2">Report it on the Sponsor Management System first, then record it here.</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id="rep-date" label="Date reported" error={form.errors.reported_on}>
                            <Input id="rep-date" type="date" max={today} min={task.triggerIso} value={form.data.reported_on} onChange={(e) => form.setData('reported_on', e.target.value)} invalid={!!form.errors.reported_on} />
                        </Field>
                        <Field id="rep-by" label="Reported by" error={form.errors.reported_by}>
                            <Input id="rep-by" value={form.data.reported_by} onChange={(e) => form.setData('reported_by', e.target.value)} invalid={!!form.errors.reported_by} placeholder="Name" />
                        </Field>
                    </div>
                    <Field id="rep-notes" label="SMS reference or notes" error={form.errors.notes} hint="For 'Not required', write the reason here.">
                        <Input id="rep-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} invalid={!!form.errors.notes} placeholder="Reference or reason" />
                    </Field>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Reported to Home Office
                        </Button>
                        <Button type="button" variant="secondary" disabled={form.processing} onClick={notRequired}>
                            Not required
                        </Button>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                    </div>
                </form>
            )}
        </dialog>
    );
}
