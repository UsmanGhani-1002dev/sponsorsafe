import { Button } from '@/components/ui/button';
import { useEffect, useRef, type ReactNode } from 'react';

/**
 * Asks before an action that matters. Uses the native <dialog>, so focus is trapped,
 * Escape closes it and focus returns to the button that opened it.
 */
export function ConfirmDialog({
    open,
    title,
    children,
    confirmLabel,
    danger = false,
    processing = false,
    onConfirm,
    onClose,
}: {
    open: boolean;
    title: string;
    children?: ReactNode;
    confirmLabel: string;
    danger?: boolean;
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
}) {
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            aria-labelledby="confirm-title"
            className="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-surface p-0 text-ink shadow-xl backdrop:bg-black/40"
        >
            <div className="flex flex-col gap-3 p-6">
                <h2 id="confirm-title" className="text-lg font-semibold">
                    {title}
                </h2>
                {children && <div className="text-[15px] text-ink-2">{children}</div>}
                <div className="mt-3 flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant={danger ? 'danger' : 'primary'} onClick={onConfirm} disabled={processing} autoFocus>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
