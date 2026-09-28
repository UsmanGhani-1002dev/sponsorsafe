import { Alert } from '@/components/ui/alert';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';

export function Flash() {
    const { flash } = usePage<SharedProps>().props;
    if (!flash.success && !flash.error) return null;
    return (
        <div className="mb-5">
            {flash.success && <Alert tone="success">{flash.success}</Alert>}
            {flash.error && <Alert tone="error">{flash.error}</Alert>}
        </div>
    );
}
