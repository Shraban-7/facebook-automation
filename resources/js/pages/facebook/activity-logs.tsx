import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Gauge,
    ShieldCheck,
    Terminal,
    Webhook,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    ActivityLogItem,
    FacebookPageSummary,
    RateLimitStatus,
    WebhookEventItem,
} from '@/types';

interface ActivityLogsProps {
    activePage: FacebookPageSummary | null;
    filters: {
        category: string;
        level: string;
    };
    logs: ActivityLogItem[];
    webhookEvents: WebhookEventItem[];
    rateLimit: RateLimitStatus;
}

export default function ActivityLogsPage({
    filters,
    logs,
    webhookEvents,
    rateLimit,
}: ActivityLogsProps) {
    const applyFilter = (category: string, level: string) => {
        router.get(
            '/facebook/activity-logs',
            { category, level },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Activity Logs, Rate Limits & Webhooks" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Activity Audit Log & Rate-Limit Telemetry
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every Graph API request, `X-Page-Usage` quota header, idempotent webhook event, and error code (`#190`, `#4`, `#200`) is recorded.
                        </p>
                    </div>

                    {/* Category Filter Pills */}
                    <div className="flex flex-wrap items-center gap-1.5">
                        {(
                            [
                                { label: 'All Logs', value: '' },
                                { label: 'API Calls', value: 'api_call' },
                                { label: 'Webhooks', value: 'webhook' },
                                { label: 'Scheduler', value: 'scheduler' },
                                { label: 'Moderation', value: 'moderation' },
                                { label: 'Errors', value: 'error' },
                            ] as const
                        ).map((item) => (
                            <Button
                                key={item.label}
                                size="sm"
                                variant={filters.category === item.value ? 'default' : 'outline'}
                                className="h-7 text-xs"
                                onClick={() => applyFilter(item.value, filters.level)}
                            >
                                {item.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Per-User Graph API Rate Limit & X-Page-Usage Telemetry Strip */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                            <Gauge className="size-4 text-blue-600 dark:text-blue-400" />
                            Per-User Graph API Quota &amp; Meta Usage Header Monitor (`X-App-Usage` / `X-Page-Usage`)
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Automatically throttles outbound jobs when usage reaches 80% or Meta returns Error Code `#4` / `#80001`.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-4">
                        <div className="rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-xs text-muted-foreground">Hourly Calls Used</span>
                            <div className="mt-1 font-mono text-xl font-bold">
                                {rateLimit.calls_used} / {rateLimit.hourly_limit}
                            </div>
                            <span className="font-mono text-[10px] text-muted-foreground">
                                {rateLimit.remaining} calls remaining
                            </span>
                        </div>

                        <div className="rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-xs text-muted-foreground">Header: call_count</span>
                            <div className="mt-1 font-mono text-xl font-bold">
                                {rateLimit.header_usage.call_count}%
                            </div>
                            <span className="font-mono text-[10px] text-muted-foreground">
                                Rolling 60m Meta window
                            </span>
                        </div>

                        <div className="rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-xs text-muted-foreground">Header: total_cputime</span>
                            <div className="mt-1 font-mono text-xl font-bold">
                                {rateLimit.header_usage.total_cputime}%
                            </div>
                            <span className="font-mono text-[10px] text-muted-foreground">
                                Graph CPU allocation
                            </span>
                        </div>

                        <div className="rounded-lg border border-border bg-muted/30 p-3">
                            <span className="text-xs text-muted-foreground">Throttle Status</span>
                            <div className="mt-1 flex items-center gap-1.5 font-mono text-sm font-semibold">
                                {rateLimit.is_throttled ? (
                                    <span className="text-red-500">THROTTLED (Backoff Active)</span>
                                ) : (
                                    <span className="text-emerald-500">HEALTHY (Within Quota)</span>
                                )}
                            </div>
                            <span className="font-mono text-[10px] text-muted-foreground">
                                Resets: {new Date(rateLimit.resets_at).toLocaleTimeString()}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Main Split: Activity Audit Log (7 Cols) + Deduped Webhook Events (5 Cols) */}
                <div className="grid gap-6 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Activity className="size-4 text-blue-600 dark:text-blue-400" />
                                    Graph API &amp; System Activity Log ({logs.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2.5">
                                {logs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="space-y-1.5 rounded-lg border border-border p-3 text-xs"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                {log.level === 'error' || log.level === 'critical' ? (
                                                    <AlertTriangle className="size-4 text-red-500" />
                                                ) : (
                                                    <CheckCircle2 className="size-4 text-emerald-500" />
                                                )}
                                                <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                    {log.category}
                                                </Badge>
                                                <span className="font-mono font-semibold">{log.action}</span>
                                                {log.graph_error_code && (
                                                    <Badge variant="destructive" className="font-mono text-[10px]">
                                                        Graph #{log.graph_error_code}
                                                        {log.graph_error_subcode
                                                            ? `/${log.graph_error_subcode}`
                                                            : ''}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 font-mono text-[11px] text-muted-foreground">
                                                {log.method && log.endpoint && (
                                                    <span>
                                                        {log.method} {log.endpoint}
                                                    </span>
                                                )}
                                                {log.http_status && <span>HTTP {log.http_status}</span>}
                                                {log.duration_ms != null && <span>{log.duration_ms}ms</span>}
                                            </div>
                                        </div>
                                        <p className="text-foreground">{log.message}</p>
                                        <div className="flex items-center justify-between font-mono text-[10px] text-muted-foreground">
                                            <span>
                                                {log.facebook_page
                                                    ? `Page: ${log.facebook_page.name}`
                                                    : 'Global Workspace'}
                                            </span>
                                            <span>{new Date(log.created_at).toLocaleString()}</span>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Deduped Webhook Event Inspector + Local ngrok Testing Guide */}
                    <div className="space-y-6 xl:col-span-5">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Webhook className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    Idempotent Webhook Event Inspector
                                </CardTitle>
                                <CardDescription>
                                    Deduplicated by deterministic <code>event_id</code> (`mid` or `comment_id`) with{' '}
                                    <code>X-Hub-Signature-256</code> HMAC verification.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {webhookEvents.map((evt) => (
                                    <div
                                        key={evt.id}
                                        className="space-y-2 rounded-lg border border-border bg-muted/20 p-3 text-xs"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="truncate font-mono text-[11px] font-semibold">
                                                {evt.event_id}
                                            </span>
                                            <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                {evt.status}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                                            <span>Type: {evt.event_type}</span>
                                            <span>·</span>
                                            {evt.signature_verified && (
                                                <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                    <ShieldCheck className="size-3" />
                                                    HMAC-SHA256 Valid
                                                </span>
                                            )}
                                        </div>
                                        <pre className="max-h-32 overflow-x-auto rounded bg-background p-2 font-mono text-[10px] text-muted-foreground">
                                            {JSON.stringify(evt.payload, null, 2)}
                                        </pre>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {/* Local ngrok & App Review Quick Reference */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Terminal className="size-4 text-amber-500" />
                                    Local Webhook Testing with ngrok
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2.5 text-xs text-muted-foreground">
                                <p>
                                    1. Start Laravel &amp; Horizon queue workers, then expose port 8000 via{' '}
                                    <code>ngrok http 8000</code>.
                                </p>
                                <p>
                                    2. In Meta App Dashboard → Webhooks, set Callback URL to{' '}
                                    <code>https://&lt;your-ngrok&gt;.ngrok-free.app/api/webhooks/facebook</code>.
                                </p>
                                <p>
                                    3. Subscribe to Page fields: <code>feed</code>, <code>messages</code>,{' '}
                                    <code>messaging_postbacks</code>.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
