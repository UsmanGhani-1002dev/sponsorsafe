import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input } from '@/components/ui/field';
import OpsLayout from '@/layouts/ops-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Pricing({ base, values }: { base: string; values: { price: string; limit: string; training: string; grace: string } }) {
    const form = useForm(values);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`${base}/pricing`, { preserveScroll: true });
    };

    return (
        <OpsLayout title="Plans and pricing" base={base}>
            <h1 className="mb-5 text-[26px] font-semibold">Plans and pricing</h1>
            <Card className="max-w-3xl p-6">
                <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field id="price" label="Monthly price (£)" error={form.errors.price}>
                            <Input id="price" inputMode="decimal" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} invalid={!!form.errors.price} />
                        </Field>
                        <Field id="limit" label="Employee limit" error={form.errors.limit}>
                            <Input id="limit" inputMode="numeric" value={form.data.limit} onChange={(e) => form.setData('limit', e.target.value)} invalid={!!form.errors.limit} />
                        </Field>
                        <Field id="training" label="1-to-1 training per person (£)" error={form.errors.training}>
                            <Input id="training" inputMode="decimal" value={form.data.training} onChange={(e) => form.setData('training', e.target.value)} invalid={!!form.errors.training} />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field id="grace" label="Grace period (days)" error={form.errors.grace} hint="After a failed payment, before access is paused.">
                            <Input id="grace" inputMode="numeric" value={form.data.grace} onChange={(e) => form.setData('grace', e.target.value)} invalid={!!form.errors.grace} />
                        </Field>
                    </div>
                    <Alert tone="info">New prices show on the website straight away. Existing subscribers keep their current price until you move them, and are emailed 30 days before any change (with online billing).</Alert>
                    <div>
                        <Button type="submit" disabled={form.processing || !form.isDirty}>
                            Save pricing
                        </Button>
                    </div>
                </form>
            </Card>
        </OpsLayout>
    );
}
