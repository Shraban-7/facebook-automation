import { Head } from '@inertiajs/react';
import { Activity, ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    providerHealth: Array<{
        provider: string;
        open: boolean;
        failures: number;
        quota: { used: number; limit: number; remaining: number } | null;
    }>;
    attempts: Array<{
        id: number;
        provider: string;
        account_name: string;
        attempt_no: number;
        status: string;
        error_class: string | null;
        error_message: string | null;
        duration_ms: number | null;
        created_at: string | null;
    }>;
    webhooks: Array<{
        id: number;
        provider: string;
        event_type: string;
        status: string;
        created_at: string | null;
    }>;
}

export default function OperationsPage({ providerHealth, attempts, webhooks }: Props) {
    return (
        <>
            <Head title="Operations & Health" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Operations, Circuit Breakers & Quotas</h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor provider circuit breakers, YouTube daily quota budget, publish attempts, and webhook ingestion.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {providerHealth.map((item) => (
                        <Card key={item.provider}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium capitalize">{item.provider}</CardTitle>
                                <Badge variant={item.open ? 'destructive' : 'default'}>
                                    {item.open ? 'Circuit Open' : 'Healthy'}
                                </Badge>
                            </CardHeader>
                            <CardContent className="space-y-1 text-xs text-muted-foreground">
                                <div>Recent failures: {item.failures}</div>
                                {item.quota && (
                                    <div>
                                        Daily Quota: {item.quota.used.toLocaleString()} / {item.quota.limit.toLocaleString()} units
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ShieldAlert className="h-4 w-4" />
                                Recent Publish Attempts ({attempts.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {attempts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No publish attempts logged yet.</p>
                            ) : (
                                attempts.map((a) => (
                                    <div key={a.id} className="py-2.5 text-xs">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium capitalize">
                                                {a.provider} · {a.account_name} (#{a.attempt_no})
                                            </span>
                                            <Badge variant={a.status === 'succeeded' ? 'default' : 'destructive'}>
                                                {a.status}
                                            </Badge>
                                        </div>
                                        {a.error_message && <p className="mt-1 text-destructive">{a.error_message}</p>}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Activity className="h-4 w-4" />
                                Recent Webhook Events ({webhooks.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {webhooks.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No webhook events received yet.</p>
                            ) : (
                                webhooks.map((w) => (
                                    <div key={w.id} className="flex items-center justify-between py-2.5 text-xs">
                                        <div>
                                            <span className="font-medium capitalize">{w.provider}</span> · {w.event_type}
                                        </div>
                                        <Badge variant="outline">{w.status}</Badge>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
