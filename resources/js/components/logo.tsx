export function Logo({ size = 28, label = true }: { size?: number; label?: boolean }) {
    return (
        <span className="inline-flex items-center gap-2.5">
            <span aria-hidden className="inline-flex items-center justify-center rounded-lg bg-accent-fill font-bold text-white" style={{ width: size, height: size, fontSize: size * 0.5 }}>
                S
            </span>
            {label && <span className="text-[17px] font-semibold tracking-tight">SponsorSafe</span>}
        </span>
    );
}
