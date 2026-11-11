import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    BarChart3,
    CalendarClock,
    CheckCircle2,
    Clock,
    Flag,
    Gauge,
    MessageSquareReply,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    Users,
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
    AnalyticsPayload,
    FacebookPageSummary,
    MessageItem,
    RateLimitStatus,
    ScheduledPostItem,
} from '@/types';

interface DashboardProps {
    pages: FacebookPageSummary[];
    activePage: FacebookPageSummary | null;
    analytics: AnalyticsPayload | null;
    upcomingSchedules: ScheduledPostItem[];
    recentMessages: MessageItem[];
    activeRulesCount: number;
    rateLimit: RateLimitStatus;
    recentLogs: ActivityLogItem[];
}

export default function Dashboard({
    pages,
    activePage,
    analytics,
    upcomingSchedules,
    recentMessages,
    activeRulesCount,
    rateLimit,
    recentLogs,
}: DashboardProps) {
    const handleSeedDemo = () => {
        router.post('/facebook/pages/seed-demo');
    };

    const handleSyncInsights = () => {
        if (!activePage) {
            return;
        }
        router.post(`/facebook/analytics/${activePage.id}/sync`, { days: 30 }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Command Overview" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Top Broadcast Command Bar */}
                <div className="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-5 shadow-xs lg:flex-row lg:items-center">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 px-2.5 py-0.5 font-mono text-xs font-medium text-blue-600 dark:text-blue-400">
                                <span className="size-1.5 rounded-full bg-blue-500" />
                                Official Meta Graph API v21.0
                            </span>
                            {activePage && (
                                <Badge
                                    variant={activePage.status === 'active' ? 'default' : 'destructive'}
                                    className="font-mono text-[11px]"
                                >
                                    Token: {activePage.status.toUpperCase()}
                                </Badge>
                            )}
                            {activePage?.webhook_subscribed && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    <ShieldCheck className="size-3.5" />
                                    X-Hub-Signature-256 Verified
                                </span>
                            )}
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {activePage ? activePage.name : 'Facebook Page Automation Studio'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {activePage
                                ? `Page ID ${activePage.page_id} · ${activePage.category ?? 'Business Page'} · All outbound Graph API operations run asynchronously via Horizon queues.`
                                : 'Connect your Facebook Pages via official Meta OAuth 2.0 or load realistic sandbox data to inspect the studio.'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {pages.length === 0 && (
                            <Button variant="outline" size="sm" onClick={handleSeedDemo}>
                                <Sparkles className="mr-1.5 size-4 text-amber-500" />
                                Load Sandbox Pages & Data
                            </Button>
                        )}
                        {activePage && (
                            <Button variant="outline" size="sm" onClick={handleSyncInsights}>
                                <RefreshCw className="mr-1.5 size-4" />
                                Queue Insights Sync
                            </Button>
                        )}
                        <Button asChild size="sm" className="bg-[#1877F2] text-white hover:bg-[#166FE5]">
                            <Link href="/facebook/scheduler">
                                <CalendarClock className="mr-1.5 size-4" />
                                Schedule Broadcast
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Empty State Onboarding Banner if no pages connected */}
                {pages.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Flag className="size-5 text-[#1877F2]" />
                                Connect Your First Facebook Page
                            </CardTitle>
                            <CardDescription>
                                Authenticate with Meta OAuth requesting only required Page permissions, or seed the local sandbox to explore all 5 modules immediately.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3">
                            <Button asChild className="bg-[#1877F2] text-white hover:bg-[#166FE5]">
                                <a href="/oauth/facebook/redirect">Connect with Facebook Login (OAuth)</a>
                            </Button>
                            <Button variant="secondary" onClick={handleSeedDemo}>
                                <Sparkles className="mr-1.5 size-4" />
                                Populate Demo Workspace
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Primary 4-Column Telemetry Strip */}
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-xs font-medium text-muted-foreground">
                                        30-Day Page Reach
                                    </CardTitle>
                                    <BarChart3 className="size-4 text-blue-600 dark:text-blue-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="font-mono text-2xl font-bold tabular-nums">
                                        {(analytics?.summary.total_reach ?? 0).toLocaleString()}
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {(analytics?.summary.total_impressions ?? 0).toLocaleString()} total impressions cached
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-xs font-medium text-muted-foreground">
                                        Engagement Rate & Actions
                                    </CardTitle>
                                    <MessageSquareReply className="size-4 text-emerald-600 dark:text-emerald-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="font-mono text-2xl font-bold tabular-nums">
                                        {analytics?.summary.engagement_rate ?? 0}%
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {(analytics?.summary.total_engagements ?? 0).toLocaleString()} engaged users · {activeRulesCount} active auto-reply rules
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-xs font-medium text-muted-foreground">
                                        Page Followers
                                    </CardTitle>
                                    <Users className="size-4 text-indigo-600 dark:text-indigo-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="font-mono text-2xl font-bold tabular-nums">
                                        {(analytics?.summary.followers ?? activePage?.followers_count ?? 0).toLocaleString()}
                                    </div>
                                    <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                        +{(analytics?.summary.net_followers ?? 0).toLocaleString()} net followers (last 30d)
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-xs font-medium text-muted-foreground">
                                        Graph API Quota (Hourly)
                                    </CardTitle>
                                    <Gauge className="size-4 text-amber-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-mono text-2xl font-bold tabular-nums">
                                            {rateLimit.calls_used}/{rateLimit.hourly_limit}
                                        </span>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {rateLimit.usage_percent}% used
                                        </span>
                                    </div>
                                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={`h-full transition-all ${
                                                rateLimit.usage_percent >= 80
                                                    ? 'bg-red-500'
                                                    : rateLimit.usage_percent >= 50
                                                      ? 'bg-amber-500'
                                                      : 'bg-emerald-500'
                                            }`}
                                            style={{ width: `${Math.min(100, Math.max(4, rateLimit.usage_percent))}%` }}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Middle Two-Column Grid: Scheduled Queue & Recent Auto-Replies */}
                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Upcoming Scheduled Posts */}
                            <Card className="flex flex-col">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base">Scheduled Broadcast Queue</CardTitle>
                                        <CardDescription>
                                            Dispatched via Laravel Scheduler & Horizon with exponential backoff
                                        </CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href="/facebook/scheduler" className="text-xs">
                                            Open Calendar
                                            <ArrowUpRight className="ml-1 size-3.5" />
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent className="flex-1 space-y-3">
                                    {upcomingSchedules.length === 0 ? (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No pending or failed scheduled posts for this Page.
                                        </p>
                                    ) : (
                                        upcomingSchedules.map((sched) => (
                                            <div
                                                key={sched.id}
                                                className="flex items-start justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                                            >
                                                <div className="min-w-0 flex-1 space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                            {sched.post?.type ?? 'post'}
                                                        </Badge>
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            {new Date(sched.scheduled_for).toLocaleString()}
                                                        </span>
                                                    </div>
                                                    <p className="line-clamp-2 text-xs text-foreground">
                                                        {sched.post?.content}
                                                    </p>
                                                    {sched.status === 'failed' && sched.error_message && (
                                                        <p className="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                                            <AlertTriangle className="size-3.5 shrink-0" />
                                                            <span className="truncate">{sched.error_message}</span>
                                                        </p>
                                                    )}
                                                </div>
                                                <Badge
                                                    variant={sched.status === 'failed' ? 'destructive' : 'secondary'}
                                                    className="shrink-0 font-mono text-[10px]"
                                                >
                                                    {sched.status}
                                                </Badge>
                                            </div>
                                        ))
                                    )}
                                </CardContent>
                            </Card>

                            {/* Recent Messenger & Comment Auto-Replies */}
                            <Card className="flex flex-col">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base">Recent Auto-Replies & Moderation</CardTitle>
                                        <CardDescription>
                                            Enforces Meta&apos;s 24-hour standard messaging window & comment filters
                                        </CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href="/facebook/auto-replies" className="text-xs">
                                            Manage Rules
                                            <ArrowUpRight className="ml-1 size-3.5" />
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent className="flex-1 space-y-3">
                                    {recentMessages.length === 0 ? (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No Messenger or comment events recorded yet.
                                        </p>
                                    ) : (
                                        recentMessages.map((msg) => (
                                            <div
                                                key={msg.id}
                                                className="space-y-1.5 rounded-lg border border-border p-3 text-xs"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                            {msg.channel}
                                                        </Badge>
                                                        <span className="font-semibold">
                                                            {msg.sender_name ?? msg.sender_id}
                                                        </span>
                                                    </div>
                                                    <Badge
                                                        variant={
                                                            msg.status === 'replied'
                                                                ? 'default'
                                                                : msg.status === 'moderated' || msg.status === 'skipped_outside_window'
                                                                  ? 'secondary'
                                                                  : 'outline'
                                                        }
                                                        className="font-mono text-[10px]"
                                                    >
                                                        {msg.status}
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground">&ldquo;{msg.message_text}&rdquo;</p>
                                                {msg.reply_text && (
                                                    <p className="rounded bg-muted/60 px-2.5 py-1.5 text-[11px] text-foreground">
                                                        <span className="font-semibold text-blue-600 dark:text-blue-400">
                                                            Auto-Reply:{' '}
                                                        </span>
                                                        {msg.reply_text}
                                                    </p>
                                                )}
                                                {msg.error_message && (
                                                    <p className="flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400">
                                                        <Clock className="size-3 shrink-0" />
                                                        {msg.error_message}
                                                    </p>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Bottom Recent Graph API & Webhook Activity Stream */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Activity className="size-4 text-blue-600 dark:text-blue-400" />
                                        Live Graph API & Webhook Audit Trail
                                    </CardTitle>
                                    <CardDescription>
                                        Every outbound Graph API call, webhook verification, and rate-limit header is logged
                                    </CardDescription>
                                </div>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href="/facebook/activity-logs" className="text-xs">
                                        Full Audit Log
                                        <ArrowUpRight className="ml-1 size-3.5" />
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y divide-border">
                                    {recentLogs.map((log) => (
                                        <div
                                            key={log.id}
                                            className="flex flex-col justify-between gap-2 py-2.5 text-xs sm:flex-row sm:items-center"
                                        >
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                {log.level === 'error' || log.level === 'critical' ? (
                                                    <AlertTriangle className="size-4 shrink-0 text-red-500" />
                                                ) : (
                                                    <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                                )}
                                                <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-medium">
                                                    {log.action}
                                                </span>
                                                <span className="truncate text-foreground">{log.message}</span>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-3 font-mono text-[11px] text-muted-foreground">
                                                {log.http_status && <span>HTTP {log.http_status}</span>}
                                                {log.duration_ms != null && <span>{log.duration_ms}ms</span>}
                                                <span>{new Date(log.created_at).toLocaleTimeString()}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}
