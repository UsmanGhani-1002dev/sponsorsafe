import { ChangePasswordForm } from '@/components/change-password';
import { Card } from '@/components/ui/card';
import OpsLayout from '@/layouts/ops-layout';

interface Props {
    base: string;
    account: { name: string; email: string; needsCode: boolean; minLength: number };
}

export default function Account({ base, account }: Props) {
    return (
        <OpsLayout title="My account" base={base}>
            <h1 className="mb-1 text-[26px] font-semibold">My account</h1>
            <p className="mb-5 text-[15px] text-ink-2">
                {account.name} · {account.email}
            </p>
            <Card className="max-w-xl p-5 sm:p-6">
                <h2 className="mb-3 text-[17px] font-semibold">Change password</h2>
                <ChangePasswordForm action={`${base}/account/password`} needsCode={account.needsCode} minLength={account.minLength} />
            </Card>
            <p className="mt-4 max-w-xl text-[13px] text-muted">
                Lost your authenticator app? Another super admin can remove your login and add it again, or run <span className="font-mono">php artisan ops:create-admin</span> with your email on the server.
            </p>
        </OpsLayout>
    );
}
