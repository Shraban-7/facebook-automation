import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    days: number;
    providerFilter: string;
    totals: {
        impressions: number;
        engagements: number;
        clicks: number;
        followers_total: number;
    };
    series: Array<{ date: string; impressions: number; engagements: number }>;
    byProvider: Array<{ provider: string; impressions: number; engagements: number }>;
}

export default function SocialAnalyticsPage({ days, providerFilter, totals, series, byProvider }: Props) {
    const updateFilter = (nextDays: number, nextProvider: string) => {
        router.get('/social/analytics', { days: nextDays, provider: nextProvider }, { preserveState: true });
    };

    return (
        <>
            <Head title="Social Analytics" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Normalized Multi-Platform Analytics</h1>
                        <p className="text-sm text-muted-foreground">
                            Cross-channel impressions, engagements, clicks, and audience totals in UTC buckets.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {[7, 30, 90].map((d) => (
                            <Button
                                key={d}
                                size="sm"
                                variant={days === d ? 'default' : 'outline'}
                                onClick={() => updateFilter(d, providerFilter)}
                            >
                                {d}d
                            </Button>
                        ))}
                        <Button size="sm" variant="outline" asChild>
                            <a href="/social/analytics/export">
                                <Download className="mr-1.5 h-3.5 w-3.5" />
                                Export CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">Impressions</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">{totals.impressions.toLocaleString()}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">Engagements</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">{totals.engagements.toLocaleString()}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">Link Clicks</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">{totals.clicks.toLocaleString()}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-medium text-muted-foreground">Total Audience</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">{totals.followers_total.toLocaleString()}</CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Breakdown by Provider</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {byProvider.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No metric snapshots recorded yet.</p>
                            ) : (
                                byProvider.map((row) => (
                                    <div key={row.provider} className="flex items-center justify-between py-2.5 text-sm">
                                        <Badge variant="outline" className="capitalize">
                                            {row.provider}
                                        </Badge>
                                        <div className="flex gap-6 text-muted-foreground">
                                            <span>{row.impressions.toLocaleString()} impr.</span>
                                            <span>{row.engagements.toLocaleString()} eng.</span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Daily Trend ({series.length} buckets)</CardTitle>
                        </CardHeader>
                        <CardContent className="max-h-72 divide-y overflow-y-auto">
                            {series.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No daily series data yet.</p>
                            ) : (
                                series.map((pt) => (
                                    <div key={pt.date} className="flex items-center justify-between py-2 text-sm">
                                        <span className="font-mono text-xs">{pt.date}</span>
                                        <div className="flex gap-6">
                                            <span>{pt.impressions.toLocaleString()} impr.</span>
                                            <span className="text-muted-foreground">{pt.engagements.toLocaleString()} eng.</span>
                                        </div>
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
