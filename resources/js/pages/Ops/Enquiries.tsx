import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import OpsLayout from '@/layouts/ops-layout';
import { router } from '@inertiajs/react';
import { Inbox } from 'lucide-react';

interface Enquiry {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    topic: string;
    message: string;
    date: string;
    handled: string | null;
}

export default function Enquiries({ base, enquiries }: { base: string; enquiries: Enquiry[] }) {
    return (
        <OpsLayout title="Enquiries and training" base={base}>
            <h1 className="mb-1 text-[26px] font-semibold">Enquiries and training</h1>
            <p className="mb-5 text-[15px] text-ink-2">From the website contact form. Reply within one working day, then mark it handled.</p>
            {enquiries.length === 0 ? (
                <Card>
                    <EmptyState icon={Inbox} title="No enquiries yet" />
                </Card>
            ) : (
                <ul className="flex flex-col gap-3">
                    {enquiries.map((e) => (
                        <li key={e.id}>
                            <Card className="flex flex-wrap items-start justify-between gap-4 p-5">
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">{e.name}</span>
                                        <Badge tone={e.topic === 'From AI chat' ? 'blue' : 'grey'}>{e.topic}</Badge>
                                        <span className="text-[13px] text-muted">{e.date}</span>
                                    </p>
                                    <p className="mt-2 text-[15px] whitespace-pre-line text-ink-2">{e.message}</p>
                                    <p className="mt-2 text-sm">
                                        <a href={`mailto:${e.email}?subject=${encodeURIComponent('Your SponsorSafe enquiry')}`} className="font-semibold text-accent hover:underline">
                                            {e.email}
                                        </a>
                                        {e.phone && <span className="text-muted"> · {e.phone}</span>}
                                    </p>
                                </div>
                                {e.handled ? (
                                    <Badge tone="green">{e.handled}</Badge>
                                ) : (
                                    <Button className="min-h-10 text-sm" onClick={() => router.post(`${base}/enquiries/${e.id}/handled`, {}, { preserveScroll: true })}>
                                        Mark handled
                                    </Button>
                                )}
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </OpsLayout>
    );
}
