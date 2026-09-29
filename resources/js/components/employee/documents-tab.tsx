import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Field, Input, Select } from '@/components/ui/field';
import { router, useForm } from '@inertiajs/react';
import { Download, FileText, Lock, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export interface DocumentCategoryRow {
    value: string;
    label: string;
    required: boolean;
    status: { text: string; tone: Tone };
    request: { id: number; since: string } | null;
    files: { id: number; name: string; size: string; uploaded: string; by: string; expiry: { text: string; tone: Tone } | null }[];
}

const opts = { preserveScroll: true, preserveState: true } as const;

/** Documents tab (compliance-rules §2): what is required, what is on file, and upload. */
export function DocumentsTab({
    employeeId,
    employeeName,
    hasPortal,
    categories,
    upload,
}: {
    employeeId: number;
    employeeName: string;
    hasPortal: boolean;
    categories: DocumentCategoryRow[];
    upload: { maxMb: number; categories: { value: string; label: string }[] };
}) {
    const firstMissing = categories.find((c) => c.required && c.files.length === 0)?.value ?? categories[0].value;
    const form = useForm<{ category: string; file: File | null; expires_on: string }>({ category: firstMissing, file: null, expires_on: '' });
    const [fileKey, setFileKey] = useState(0);
    const [removing, setRemoving] = useState<{ id: number; name: string } | null>(null);
    const first = employeeName.split(' ')[0];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/app/employees/${employeeId}/documents`, {
            ...opts,
            forceFormData: true,
            onSuccess: () => {
                form.reset('file', 'expires_on');
                setFileKey((k) => k + 1); // clear the file input
            },
        });
    };

    return (
        <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <Card className="overflow-hidden">
                <ul>
                    {categories.map((c) => (
                        <li key={c.value} className="flex flex-col gap-2 border-t border-line px-5 py-4 first:border-t-0">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-[15px] font-semibold">{c.label}</p>
                                    <p className="text-[13px] text-muted">{c.required ? 'Required' : 'Optional'}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {c.files.length === 0 && !c.request && c.required && (
                                        <Button variant="secondary" className="min-h-10 px-3 text-sm" onClick={() => router.post(`/app/employees/${employeeId}/document-requests`, { category: c.value }, opts)}>
                                            Request from employee
                                        </Button>
                                    )}
                                    {c.request && (
                                        <Button variant="ghost" className="min-h-10 px-3 text-sm" onClick={() => router.post(`/app/document-requests/${c.request!.id}/cancel`, {}, opts)}>
                                            Cancel request
                                        </Button>
                                    )}
                                    <Badge tone={c.status.tone}>
                                        {c.status.text}
                                        {c.request ? ` · ${c.request.since}` : ''}
                                    </Badge>
                                </div>
                            </div>
                            {c.files.map((f) => (
                                <div key={f.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-canvas px-3 py-2">
                                    <div className="flex min-w-0 items-center gap-2.5">
                                        <FileText size={18} aria-hidden className="shrink-0 text-muted" />
                                        <div className="min-w-0">
                                            <a href={`/app/documents/${f.id}`} target="_blank" rel="noopener" className="block truncate font-mono text-[13px] font-semibold text-accent hover:underline">
                                                {f.name}
                                            </a>
                                            <p className="text-[12px] text-muted">
                                                {f.size} · Uploaded {f.uploaded} by {f.by}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {f.expiry && <Badge tone={f.expiry.tone}>{f.expiry.text}</Badge>}
                                        <a href={`/app/documents/${f.id}/download`} aria-label={`Download ${f.name}`} className="inline-flex size-10 items-center justify-center rounded-lg text-muted hover:bg-surface hover:text-ink">
                                            <Download size={17} aria-hidden />
                                        </a>
                                        <button type="button" onClick={() => setRemoving(f)} aria-label={`Remove ${f.name}`} className="inline-flex size-10 items-center justify-center rounded-lg text-muted hover:bg-surface hover:text-red-700">
                                            <Trash2 size={17} aria-hidden />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </li>
                    ))}
                </ul>
            </Card>

            <Card className="p-5 sm:p-6 lg:sticky lg:top-6">
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <h2 className="text-[17px] font-semibold">Upload document</h2>
                    <Field id="doc-category" label="Category" error={form.errors.category}>
                        <Select id="doc-category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                            {upload.categories.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field id="doc-file" label="File" error={form.errors.file} hint={`PDF, JPG or PNG, up to ${upload.maxMb} MB.`}>
                        <input
                            key={fileKey}
                            id="doc-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            aria-invalid={!!form.errors.file || undefined}
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            className="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-semibold file:text-accent-strong"
                        />
                    </Field>
                    <Field id="doc-expiry" label="Expiry date (if any)" error={form.errors.expires_on}>
                        <Input id="doc-expiry" type="date" value={form.data.expires_on} onChange={(e) => form.setData('expires_on', e.target.value)} />
                    </Field>
                    {form.progress && (
                        <progress value={form.progress.percentage} max={100} className="h-2 w-full accent-[#4F46E5]" aria-label="Upload progress">
                            {form.progress.percentage}%
                        </progress>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        Upload
                    </Button>
                    <p className="flex gap-2 text-[13px] text-muted">
                        <Lock size={14} aria-hidden className="mt-0.5 shrink-0" />
                        Stored encrypted, outside the public web root. Only your admins can view or download it, and every view is logged.
                    </p>
                    {!hasPortal && <p className="text-[13px] text-muted">{first} has no portal access, so requests will wait until you send an invite.</p>}
                </form>
            </Card>

            <ConfirmDialog
                open={removing !== null}
                title={`Remove ${removing?.name ?? ''}?`}
                confirmLabel="Remove"
                danger
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && router.delete(`/app/documents/${removing.id}`, { ...opts, onFinish: () => setRemoving(null) })}
            >
                The file is deleted permanently. Only remove documents uploaded by mistake; keep compliance evidence for the retention period.
            </ConfirmDialog>
        </div>
    );
}
