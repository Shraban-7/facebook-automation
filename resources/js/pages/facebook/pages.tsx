import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Copy,
    KeyRound,
    Lock,
    Plus,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    Trash2,
    Webhook,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FacebookPageSummary } from '@/types';

interface PagesProps {
    pages: FacebookPageSummary[];
    activePageId: number | null;
    requiredScopes: string[];
    oauthConfigured: boolean;
    webhookCallbackUrl: string;
    webhookVerifyToken: string;
    graphVersion: string;
}

export default function FacebookPagesPage({
    pages,
    activePageId,
    requiredScopes,
    webhookCallbackUrl,
    webhookVerifyToken,
    graphVersion,
}: PagesProps) {
    const [manualDialogOpen, setManualDialogOpen] = useState(false);
    const [copiedField, setCopiedField] = useState<string | null>(null);

    const manualForm = useForm({
        page_id: '',
        name: '',
        category: 'Brand & Business',
        access_token: '',
        followers_count: 0,
    });

    const copyText = (key: string, value: string) => {
        void navigator.clipboard.writeText(value);
        setCopiedField(key);
        setTimeout(() => setCopiedField(null), 1800);
    };

    const submitManualPage = (e: React.FormEvent) => {
        e.preventDefault();
        manualForm.post('/facebook/pages/manual', {
            onSuccess: () => {
                setManualDialogOpen(false);
                manualForm.reset();
            },
        });
    };

    return (
        <>
            <Head title="Connected Facebook Pages" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Facebook Page Connections & OAuth 2.0
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Long-lived Page access tokens are encrypted at rest (`AES-256-CBC`) and refreshed automatically via scheduled jobs.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post('/facebook/pages/seed-demo')}
                        >
                            <Sparkles className="mr-1.5 size-4 text-amber-500" />
                            Seed Sandbox Pages
                        </Button>

                        <Dialog open={manualDialogOpen} onOpenChange={setManualDialogOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Plus className="mr-1.5 size-4" />
                                    Connect Token Manually
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Connect Facebook Page Token</DialogTitle>
                                    <DialogDescription>
                                        Provide a long-lived Page Access Token from Meta Graph API Explorer or System User for local testing.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={submitManualPage} className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="page_id">Facebook Page ID</Label>
                                        <Input
                                            id="page_id"
                                            placeholder="e.g. 108492019482710"
                                            value={manualForm.data.page_id}
                                            onChange={(e) => manualForm.setData('page_id', e.target.value)}
                                            required
                                        />
                                        {manualForm.errors.page_id && (
                                            <p className="text-xs text-red-500">{manualForm.errors.page_id}</p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="name">Page Name</Label>
                                        <Input
                                            id="name"
                                            placeholder="e.g. Acme Design Studio"
                                            value={manualForm.data.name}
                                            onChange={(e) => manualForm.setData('name', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="category">Category</Label>
                                        <Input
                                            id="category"
                                            value={manualForm.data.category}
                                            onChange={(e) => manualForm.setData('category', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="access_token">Long-Lived Page Access Token</Label>
                                        <Input
                                            id="access_token"
                                            type="password"
                                            placeholder="EAABsbCS1iHgBO..."
                                            value={manualForm.data.access_token}
                                            onChange={(e) => manualForm.setData('access_token', e.target.value)}
                                            required
                                        />
                                        {manualForm.errors.access_token && (
                                            <p className="text-xs text-red-500">{manualForm.errors.access_token}</p>
                                        )}
                                    </div>
                                    <DialogFooter>
                                        <Button type="submit" disabled={manualForm.processing}>
                                            Encrypt & Connect Page
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <Button asChild size="sm" className="bg-[#1877F2] text-white hover:bg-[#166FE5]">
                            <a href="/oauth/facebook/redirect">
                                <KeyRound className="mr-1.5 size-4" />
                                Connect with Facebook Login (OAuth)
                            </a>
                        </Button>
                    </div>
                </div>

                {/* OAuth Scopes & Webhook Handshake Reference */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <Lock className="size-4 text-blue-600 dark:text-blue-400" />
                                Principle of Least Privilege — Requested OAuth Scopes
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Only these 5 official Graph API permissions are requested during the OAuth 2.0 flow ({graphVersion}):
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {requiredScopes.map((scope) => (
                                <span
                                    key={scope}
                                    className="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/50 px-2.5 py-1 font-mono text-xs"
                                >
                                    <CheckCircle2 className="size-3.5 text-emerald-500" />
                                    {scope}
                                </span>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <Webhook className="size-4 text-emerald-600 dark:text-emerald-400" />
                                Meta App Webhook Endpoint & Verify Token
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Configure these values in Meta App Dashboard → Webhooks (Page object: `feed`, `messages`, `messaging_postbacks`).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2.5 text-xs">
                            <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/40 px-3 py-1.5">
                                <div className="min-w-0">
                                    <span className="text-[10px] font-medium text-muted-foreground uppercase">Callback URL: </span>
                                    <code className="font-mono text-foreground">{webhookCallbackUrl}</code>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={() => copyText('url', webhookCallbackUrl)}
                                >
                                    <Copy className="mr-1 size-3" />
                                    {copiedField === 'url' ? 'Copied' : 'Copy'}
                                </Button>
                            </div>
                            <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/40 px-3 py-1.5">
                                <div className="min-w-0">
                                    <span className="text-[10px] font-medium text-muted-foreground uppercase">Verify Token: </span>
                                    <code className="font-mono text-foreground">{webhookVerifyToken}</code>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={() => copyText('token', webhookVerifyToken)}
                                >
                                    <Copy className="mr-1 size-3" />
                                    {copiedField === 'token' ? 'Copied' : 'Copy'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Connected Pages Grid */}
                <div className="grid gap-4 md:grid-cols-2">
                    {pages.map((page) => {
                        const isActiveWorkspace = page.id === activePageId;
                        return (
                            <Card
                                key={page.id}
                                className={
                                    isActiveWorkspace
                                        ? 'border-blue-500/60 ring-1 ring-blue-500/20'
                                        : ''
                                }
                            >
                                <CardHeader className="flex flex-row items-start justify-between gap-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted font-semibold">
                                            {page.avatar_url ? (
                                                <img
                                                    src={page.avatar_url}
                                                    alt={page.name}
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                page.name.slice(0, 2).toUpperCase()
                                            )}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-base">{page.name}</CardTitle>
                                                {isActiveWorkspace && (
                                                    <Badge className="bg-blue-600 text-[10px] text-white">
                                                        Active Workspace
                                                    </Badge>
                                                )}
                                            </div>
                                            <CardDescription className="font-mono text-xs">
                                                ID: {page.page_id} · {page.category ?? 'Page'} ·{' '}
                                                {page.followers_count.toLocaleString()} followers
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <Badge
                                        variant={
                                            page.status === 'active'
                                                ? 'default'
                                                : page.status === 'expired'
                                                  ? 'destructive'
                                                  : 'secondary'
                                        }
                                        className="font-mono text-[10px] uppercase"
                                    >
                                        {page.status}
                                    </Badge>
                                </CardHeader>

                                <CardContent className="space-y-4">
                                    {/* Token & Webhook telemetry */}
                                    <div className="grid grid-cols-2 gap-2 rounded-lg border border-border bg-muted/30 p-3 text-xs">
                                        <div>
                                            <span className="text-muted-foreground">Encrypted Token Expiry</span>
                                            <p className="mt-0.5 font-mono font-medium">
                                                {page.token_expires_at
                                                    ? new Date(page.token_expires_at).toLocaleDateString()
                                                    : 'Never-expiring Page Token'}
                                                {page.is_expiring_soon && (
                                                    <span className="ml-1.5 text-amber-500">(Expiring soon)</span>
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Webhook Subscription</span>
                                            <p className="mt-0.5 flex items-center gap-1 font-medium">
                                                <ShieldCheck className="size-3.5 text-emerald-500" />
                                                {page.webhook_subscribed ? 'Subscribed (feed, messages)' : 'Pending'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Counts */}
                                    {page.counts && (
                                        <div className="grid grid-cols-4 gap-2 text-center text-xs">
                                            <div className="rounded border border-border p-2">
                                                <div className="font-mono font-semibold">{page.counts.posts}</div>
                                                <div className="text-[10px] text-muted-foreground">Posts</div>
                                            </div>
                                            <div className="rounded border border-border p-2">
                                                <div className="font-mono font-semibold">{page.counts.scheduled_posts}</div>
                                                <div className="text-[10px] text-muted-foreground">Schedules</div>
                                            </div>
                                            <div className="rounded border border-border p-2">
                                                <div className="font-mono font-semibold">{page.counts.auto_reply_rules}</div>
                                                <div className="text-[10px] text-muted-foreground">Rules</div>
                                            </div>
                                            <div className="rounded border border-border p-2">
                                                <div className="font-mono font-semibold">{page.counts.messages}</div>
                                                <div className="text-[10px] text-muted-foreground">Messages</div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                                        <div className="flex items-center gap-2">
                                            {!isActiveWorkspace && (
                                                <Button
                                                    size="sm"
                                                    variant="default"
                                                    onClick={() => router.post(`/facebook/pages/${page.id}/switch`)}
                                                >
                                                    Switch to Page
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => router.post(`/facebook/pages/${page.id}/refresh-token`)}
                                            >
                                                <RefreshCw className="mr-1.5 size-3.5" />
                                                Refresh Token
                                            </Button>
                                        </div>

                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-red-600 hover:bg-red-500/10 hover:text-red-600"
                                            onClick={() => {
                                                if (confirm(`Disconnect ${page.name}?`)) {
                                                    router.delete(`/facebook/pages/${page.id}`);
                                                }
                                            }}
                                        >
                                            <Trash2 className="mr-1 size-3.5" />
                                            Disconnect
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
