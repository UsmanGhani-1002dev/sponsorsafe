import { Logo } from '@/components/logo';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import type { SharedProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Account {
    name: string;
    email: string;
    initials: string;
    business: string | null;
    role: string;
}

export default function Login({ account }: { account: Account | null }) {
    const { flash } = usePage<SharedProps>().props;
    const emailForm = useForm({ email: '' });
    const pwForm = useForm({ password: '', remember: false });

    const next = (e: FormEvent) => {
        e.preventDefault();
        emailForm.post('/login/lookup', { preserveScroll: true });
    };
    const signIn = (e: FormEvent) => {
        e.preventDefault();
        pwForm.post('/login', { onFinish: () => pwForm.reset('password') });
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6 px-4 py-12">
            <Head title="Sign in" />
            <Logo size={34} />
            <div className="w-full max-w-[420px] rounded-2xl border border-line bg-white p-8 shadow-[0_4px_8px_-2px_rgba(16,24,40,0.08)]">
                <div className="mb-6">
                    <h1 className="text-[22px] font-semibold">Sign in</h1>
                    <p className="text-sm text-muted">For business admins and employees</p>
                </div>

                {flash.error && (
                    <div className="mb-4">
                        <Alert>{flash.error}</Alert>
                    </div>
                )}

                {!account ? (
                    <form onSubmit={next} className="flex flex-col gap-4" noValidate>
                        <Field id="email" label="Email" error={emailForm.errors.email}>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                autoFocus
                                value={emailForm.data.email}
                                onChange={(e) => emailForm.setData('email', e.target.value)}
                                invalid={!!emailForm.errors.email}
                                aria-describedby={emailForm.errors.email ? 'email-error' : undefined}
                                placeholder="you@company.co.uk"
                            />
                        </Field>
                        <Button type="submit" disabled={emailForm.processing}>
                            Next
                        </Button>
                    </form>
                ) : (
                    <form onSubmit={signIn} className="flex flex-col gap-4" noValidate>
                        <div className="flex items-center justify-between gap-3 rounded-xl border border-line bg-canvas p-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <span aria-hidden className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[15px] font-semibold text-accent-strong">
                                    {account.initials}
                                </span>
                                <div className="min-w-0">
                                    <p className="font-semibold">{account.name}</p>
                                    <p className="truncate text-[13px] text-muted">{account.email}</p>
                                    <p className="text-[13px] text-muted">
                                        {account.business} · {account.role}
                                    </p>
                                </div>
                            </div>
                            <button type="button" onClick={() => router.post('/login/reset')} className="shrink-0 rounded-md px-2 py-1.5 text-sm font-semibold text-accent hover:bg-accent-soft">
                                Not you?
                            </button>
                        </div>
                        <input type="email" name="email" autoComplete="username" value={account.email} readOnly hidden />
                        <Field id="password" label="Password" error={pwForm.errors.password}>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                autoFocus
                                value={pwForm.data.password}
                                onChange={(e) => pwForm.setData('password', e.target.value)}
                                invalid={!!pwForm.errors.password}
                                aria-describedby={pwForm.errors.password ? 'password-error' : undefined}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm text-ink-2">
                            <input type="checkbox" className="size-4 accent-[#4F46E5]" checked={pwForm.data.remember} onChange={(e) => pwForm.setData('remember', e.target.checked)} />
                            Keep me signed in on this device
                        </label>
                        <Button type="submit" disabled={pwForm.processing}>
                            Sign in
                        </Button>
                    </form>
                )}
            </div>
            <p className="max-w-[420px] text-center text-[13px] text-muted">Employees are invited by their employer. Forgotten your password? Ask your admin to resend your invite (self-service reset arrives with email setup).</p>
        </div>
    );
}
