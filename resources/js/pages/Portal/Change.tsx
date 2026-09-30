import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import PortalLayout from '@/layouts/portal-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    kinds: { value: string; label: string; field: string }[];
    preselect: string;
    current: Record<string, string | null>;
}

const hints: Record<string, string> = {
    address: 'Your employer must keep your current UK address on file.',
    visa: 'HR will ask for your new share code and do a new right-to-work check. You can add the share code in the note.',
    email: 'This is also the email you sign in with.',
    name: 'HR may ask for evidence, such as a marriage certificate or deed poll.',
};

export default function UpdateMyDetails({ kinds, preselect, current }: Props) {
    const form = useForm({ kind: preselect, value: '', note: '' });
    const kind = kinds.find((k) => k.value === form.data.kind) ?? kinds[0];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/me/update-details', { preserveScroll: true });
    };

    return (
        <PortalLayout title="Update my details">
            <h1 className="text-[24px] font-semibold">Update my details</h1>
            <p className="mt-1 text-[15px] text-ink-2">Your employer must keep your details up to date. Tell HR as soon as something changes.</p>

            <Card className="mt-5 p-4 sm:p-6">
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <Field id="kind" label="What has changed?" error={form.errors.kind}>
                        <Select id="kind" value={form.data.kind} onChange={(e) => form.setData({ kind: e.target.value, value: '', note: form.data.note })}>
                            {kinds.map((k) => (
                                <option key={k.value} value={k.value}>
                                    {k.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {current[kind.value] && (
                        <p className="text-sm text-ink-2">
                            Currently: <strong>{current[kind.value]}</strong>
                        </p>
                    )}
                    <Field id="value" label={kind.field} error={form.errors.value}>
                        <Input
                            id="value"
                            type={kind.value === 'visa' ? 'date' : kind.value === 'email' ? 'email' : kind.value === 'phone' ? 'tel' : 'text'}
                            value={form.data.value}
                            onChange={(e) => form.setData('value', e.target.value)}
                            invalid={!!form.errors.value}
                        />
                    </Field>
                    <Field id="note" label="Anything else HR should know (optional)" error={form.errors.note}>
                        <Input id="note" maxLength={300} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                    </Field>
                    {hints[kind.value] && <Alert tone="info">{hints[kind.value]}</Alert>}
                    <Button type="submit" disabled={form.processing}>
                        Send to HR
                    </Button>
                </form>
            </Card>
        </PortalLayout>
    );
}
