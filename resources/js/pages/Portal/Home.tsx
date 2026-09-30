import { RequestList, type MyRequest } from '@/components/portal';
import { Card } from '@/components/ui/card';
import type { Tone } from '@/components/ui/badge';
import PortalLayout from '@/layouts/portal-layout';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { CalendarPlus, Home as HomeIcon, Thermometer } from 'lucide-react';

interface Props {
    first: string;
    business: string;
    leave: { year: string; allowance: string; taken: number; pending: number; left: string };
    documentsNeeded: number;
    rightToWork: { title: string; note: string; tone: Tone };
    recent: MyRequest[];
}

const action = 'flex min-h-12 items-center gap-2.5 rounded-lg border border-line-strong bg-surface px-4 text-[15px] font-semibold hover:bg-canvas';

export default function PortalHome({ first, leave, documentsNeeded, rightToWork, recent }: Props) {
    return (
        <PortalLayout title="Home">
            <h1 className="text-[26px] font-semibold">Hello, {first}</h1>

            <div className="mt-5 grid gap-4 sm:grid-cols-3">
                <Card className="p-5">
                    <p className="text-sm text-muted">Annual leave left in {leave.year}</p>
                    <p className="mt-1 font-mono text-3xl font-semibold">{leave.left}</p>
                    <p className="text-[13px] text-muted">
                        of {leave.allowance} days{leave.pending ? ` · ${leave.pending} waiting for HR` : ''}
                    </p>
                </Card>
                <Card className="flex flex-col p-5">
                    <p className="text-sm text-muted">Documents HR needs from you</p>
                    <p className={cn('mt-1 font-mono text-3xl font-semibold', documentsNeeded > 0 && 'text-amber-700 dark:text-amber-300')}>{documentsNeeded}</p>
                    {documentsNeeded > 0 && (
                        <Link href="/me/documents" className="mt-1 text-sm font-semibold text-accent hover:underline">
                            Go to my documents
                        </Link>
                    )}
                </Card>
                <Card className="p-5">
                    <p className="text-sm text-muted">Right to work</p>
                    <p className="mt-1 font-semibold">{rightToWork.title}</p>
                    <p className={cn('mt-1 text-[13px]', rightToWork.tone === 'amber' ? 'text-amber-700 dark:text-amber-300' : 'text-muted')}>{rightToWork.note}</p>
                </Card>
            </div>

            <h2 className="mt-8 mb-3 text-lg font-semibold">Quick actions</h2>
            <div className="grid gap-3 sm:grid-cols-3">
                <Link href="/me/leave" className={action}>
                    <CalendarPlus size={18} aria-hidden className="text-accent" /> Request leave
                </Link>
                <Link href="/me/leave?type=sick" className={action}>
                    <Thermometer size={18} aria-hidden className="text-accent" /> Report sickness
                </Link>
                <Link href="/me/update-details?kind=address" className={action}>
                    <HomeIcon size={18} aria-hidden className="text-accent" /> Update my address
                </Link>
            </div>

            <div className="mt-8 mb-3 flex items-baseline justify-between">
                <h2 className="text-lg font-semibold">Recent requests</h2>
                {recent.length > 0 && (
                    <Link href="/me/requests" className="text-sm font-semibold text-accent hover:underline">
                        All requests
                    </Link>
                )}
            </div>
            <Card className="overflow-hidden">{recent.length ? <RequestList rows={recent} /> : <p className="p-5 text-sm text-ink-2">You haven't sent any requests yet.</p>}</Card>
        </PortalLayout>
    );
}
