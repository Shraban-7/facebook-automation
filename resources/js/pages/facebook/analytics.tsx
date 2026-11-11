import { Head, router } from '@inertiajs/react';
import {
    BarChart3,
    ExternalLink,
    Heart,
    MessageCircle,
    RefreshCw,
    Share2,
    TrendingUp,
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
import type { AnalyticsPayload, FacebookPageSummary } from '@/types';

interface AnalyticsProps {
    activePage: FacebookPageSummary | null;
    days: number;
    analytics: AnalyticsPayload | null;
}

export default function AnalyticsPage({ activePage, days, analytics }: AnalyticsProps) {
    const series = analytics?.series ?? [];
    const maxReach = Math.max(1, ...series.map((s) => s.reach));
    const maxEngagement = Math.max(1, ...series.map((s) => s.engagement));

    const handleChangeRange = (newDays: number) => {
        router.get('/facebook/analytics', { days: newDays }, { preserveScroll: true });
    };

    const handleSync = () => {
        if (!activePage) {
            return;
        }
        router.post(`/facebook/analytics/${activePage.id}/sync`, { days }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Page Analytics & Insights" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header & 7/30/90 Day Selector */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Page Insights & Performance Analytics
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pulled on an hourly schedule from Meta Graph API (`/insights`) and cached in Redis.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-1 rounded-lg border border-border bg-muted/40 p-1">
                            {[7, 30, 90].map((d) => (
                                <Button
                                    key={d}
                                    variant={days === d ? 'default' : 'ghost'}
                                    size="sm"
                                    className="h-7 px-3 font-mono text-xs"
                                    onClick={() => handleChangeRange(d)}
                                >
                                    {d} Days
                                </Button>
                            ))}
                        </div>

                        {activePage && (
                            <Button variant="outline" size="sm" onClick={handleSync}>
                                <RefreshCw className="mr-1.5 size-3.5" />
                                Pull Fresh Insights
                            </Button>
                        )}
                    </div>
                </div>

                {/* Summary KPIs */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">
                                Unique Page Reach ({days}d)
                            </CardTitle>
                            <BarChart3 className="size-4 text-blue-600 dark:text-blue-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="font-mono text-2xl font-bold tabular-nums">
                                {(analytics?.summary.total_reach ?? 0).toLocaleString()}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {(analytics?.summary.total_impressions ?? 0).toLocaleString()} total impressions
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">
                                Post Engagements ({days}d)
                            </CardTitle>
                            <TrendingUp className="size-4 text-emerald-600 dark:text-emerald-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="font-mono text-2xl font-bold tabular-nums">
                                {(analytics?.summary.total_engagements ?? 0).toLocaleString()}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {analytics?.summary.engagement_rate ?? 0}% average engagement rate
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">
                                Total Page Followers
                            </CardTitle>
                            <Users className="size-4 text-indigo-600 dark:text-indigo-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="font-mono text-2xl font-bold tabular-nums">
                                {(analytics?.summary.followers ?? 0).toLocaleString()}
                            </div>
                            <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                +{(analytics?.summary.net_followers ?? 0).toLocaleString()} net growth in {days}d
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">
                                Page Views ({days}d)
                            </CardTitle>
                            <BarChart3 className="size-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="font-mono text-2xl font-bold tabular-nums">
                                {(analytics?.summary.page_views ?? 0).toLocaleString()}
                            </div>
                            <p className="mt-1 font-mono text-[11px] text-muted-foreground">
                                Cached:{' '}
                                {analytics?.cached_at
                                    ? new Date(analytics.cached_at).toLocaleTimeString()
                                    : 'Live'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Reach & Impressions Bar Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Daily Reach Trend (Last {days} Days)
                            </CardTitle>
                            <CardDescription>
                                Official Graph API metric: <code>page_impressions_unique</code>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-56 items-end gap-1 pt-4">
                                {series.map((point) => {
                                    const heightPct = Math.max(6, Math.round((point.reach / maxReach) * 100));
                                    return (
                                        <div
                                            key={point.iso_date}
                                            className="group relative flex h-full flex-1 flex-col justify-end"
                                        >
                                            <div className="pointer-events-none absolute -top-7 left-1/2 z-10 hidden -translate-x-1/2 rounded bg-foreground px-1.5 py-0.5 font-mono text-[10px] whitespace-nowrap text-background group-hover:block">
                                                {point.date}: {point.reach.toLocaleString()}
                                            </div>
                                            <div
                                                className="w-full rounded-t bg-[#1877F2]/80 transition-all group-hover:bg-[#1877F2]"
                                                style={{ height: `${heightPct}%` }}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="mt-2 flex justify-between font-mono text-[10px] text-muted-foreground">
                                <span>{series[0]?.date}</span>
                                <span>{series[Math.floor(series.length / 2)]?.date}</span>
                                <span>{series[series.length - 1]?.date}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Engaged Users & Follower Growth Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Engaged Users & Daily Follower Gains (Last {days} Days)
                            </CardTitle>
                            <CardDescription>
                                Official Graph API metrics: <code>page_post_engagements</code> &amp;{' '}
                                <code>page_follows</code>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-56 items-end gap-1 pt-4">
                                {series.map((point) => {
                                    const heightPct = Math.max(
                                        6,
                                        Math.round((point.engagement / maxEngagement) * 100),
                                    );
                                    return (
                                        <div
                                            key={point.iso_date}
                                            className="group relative flex h-full flex-1 flex-col justify-end"
                                        >
                                            <div className="pointer-events-none absolute -top-7 left-1/2 z-10 hidden -translate-x-1/2 rounded bg-foreground px-1.5 py-0.5 font-mono text-[10px] whitespace-nowrap text-background group-hover:block">
                                                {point.date}: {point.engagement.toLocaleString()} engaged (+
                                                {point.net_followers} followers)
                                            </div>
                                            <div
                                                className="w-full rounded-t bg-emerald-500/80 transition-all group-hover:bg-emerald-500"
                                                style={{ height: `${heightPct}%` }}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="mt-2 flex justify-between font-mono text-[10px] text-muted-foreground">
                                <span>{series[0]?.date}</span>
                                <span>{series[Math.floor(series.length / 2)]?.date}</span>
                                <span>{series[series.length - 1]?.date}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Top-Performing Published Posts */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Top-Performing Published Posts</CardTitle>
                        <CardDescription>
                            Ranked by combined unique reach and engagement velocity.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            {(analytics?.top_posts ?? []).map((post, idx) => (
                                <div
                                    key={post.id}
                                    className="flex flex-col justify-between rounded-xl border border-border p-4"
                                >
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                #{idx + 1} · {post.type}
                                            </Badge>
                                            {post.published_at && (
                                                <span className="font-mono text-[11px] text-muted-foreground">
                                                    {new Date(post.published_at).toLocaleDateString()}
                                                </span>
                                            )}
                                        </div>
                                        <p className="line-clamp-3 text-sm text-foreground">{post.content}</p>
                                    </div>

                                    <div className="mt-4 space-y-2.5 border-t border-border pt-3">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-muted-foreground">Reach</span>
                                            <span className="font-mono font-semibold">
                                                {post.reach.toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                                            <span className="inline-flex items-center gap-1">
                                                <Heart className="size-3.5 text-rose-500" />
                                                {post.reactions.toLocaleString()}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <MessageCircle className="size-3.5 text-blue-500" />
                                                {post.comments.toLocaleString()}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <Share2 className="size-3.5 text-emerald-500" />
                                                {post.shares.toLocaleString()}
                                            </span>
                                            {post.permalink_url && (
                                                <a
                                                    href={post.permalink_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-0.5 text-blue-600 hover:underline dark:text-blue-400"
                                                >
                                                    Post
                                                    <ExternalLink className="size-3" />
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
