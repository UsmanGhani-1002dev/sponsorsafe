import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import PortalLayout from '@/layouts/portal-layout';
import { useForm } from '@inertiajs/react';
import { FileText, Lock } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Props {
    requested: { id: number; label: string; since: string }[];
    onFile: { id: number; category: string; name: string; date: string; waiting: boolean }[];
    categories: { value: string; label: string }[];
    maxMb: number;
}

const fileInput =
    'min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-semibold file:text-accent-strong';

export default function MyDocuments({ requested, onFile, categories, maxMb }: Props) {
    return (
        <PortalLayout title="My documents">
            <h1 className="text-[26px] font-semibold">My documents</h1>

            <h2 className="mt-6 mb-3 text-lg font-semibold">Requested by HR</h2>
            {requested.length === 0 ? (
                <Card className="p-5 text-sm text-ink-2">Nothing needed right now.</Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {requested.map((r) => (
                        <RequestedUpload key={r.id} request={r} maxMb={maxMb} />
                    ))}
                </div>
            )}

            <h2 className="mt-8 mb-3 text-lg font-semibold">On file with HR</h2>
            <Card className="overflow-hidden">
                {onFile.length === 0 ? (
                    <p className="p-5 text-sm text-ink-2">No documents yet.</p>
                ) : (
                    <ul>
                        {onFile.map((d) => (
                            <li key={d.id} className="flex items-center justify-between gap-3 border-t border-line px-4 py-3 first:border-t-0 sm:px-5">
                                <div className="flex min-w-0 items-center gap-2.5">
                                    <FileText size={18} aria-hidden className="shrink-0 text-muted" />
                                    <div className="min-w-0">
                                        <p className="text-[13px] text-muted">{d.category}</p>
                                        <a href={`/me/documents/${d.id}`} target="_blank" rel="noopener" className="block truncate font-medium text-accent hover:underline">
                                            {d.name}
                                        </a>
                                    </div>
                                </div>
                                {d.waiting ? <Badge tone="blue">Waiting for HR</Badge> : <span className="shrink-0 text-[13px] text-muted">{d.date}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <h2 className="mt-8 mb-3 text-lg font-semibold">Send another document</h2>
            <SendAnother categories={categories} maxMb={maxMb} />
            <p className="mt-3 flex gap-2 text-[13px] text-muted">
                <Lock size={14} aria-hidden className="mt-0.5 shrink-0" /> Documents are stored encrypted and only your employer's HR admins can open them.
            </p>
        </PortalLayout>
    );
}

function RequestedUpload({ request, maxMb }: { request: Props['requested'][number]; maxMb: number }) {
    const form = useForm<{ document_request_id: number; file: File | null }>({ document_request_id: request.id, file: null });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/me/documents', { forceFormData: true, preserveScroll: true });
    };
    return (
        <Card className="p-4 sm:p-5">
            <form onSubmit={submit} noValidate className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="font-semibold">{request.label}</p>
                        <p className="text-[13px] text-muted">Requested {request.since}</p>
                    </div>
                    <Badge tone="amber">Action needed</Badge>
                </div>
                <Field id={`req-${request.id}`} label={`File for ${request.label}`} error={form.errors.file} hint={`PDF, JPG or PNG, up to ${maxMb} MB. A clear photo is fine.`}>
                    <input id={`req-${request.id}`} type="file" accept=".pdf,.jpg,.jpeg,.png" capture="environment" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className={fileInput} />
                </Field>
                <Button type="submit" disabled={form.processing || !form.data.file}>
                    Send
                </Button>
            </form>
        </Card>
    );
}

function SendAnother({ categories, maxMb }: { categories: Props['categories']; maxMb: number }) {
    const [key, setKey] = useState(0);
    const form = useForm<{ category: string; file: File | null; note: string }>({ category: categories[0].value, file: null, note: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/me/documents', { forceFormData: true, preserveScroll: true, onSuccess: () => (form.reset('file', 'note'), setKey((k) => k + 1)) });
    };
    return (
        <Card className="p-4 sm:p-5">
            <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                <Field id="up-category" label="What is it?" error={form.errors.category}>
                    <Select id="up-category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                        {categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field id="up-file" label="File" error={form.errors.file} hint={`PDF, JPG or PNG, up to ${maxMb} MB.`}>
                    <input key={key} id="up-file" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className={fileInput} />
                </Field>
                <Field id="up-note" label="Note for HR (optional)" error={form.errors.note}>
                    <Input id="up-note" maxLength={300} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                </Field>
                <Button type="submit" disabled={form.processing}>
                    Send to HR
                </Button>
            </form>
        </Card>
    );
}
