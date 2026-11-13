import { Head, router, useForm } from '@inertiajs/react';
import { Check, CheckCircle2, Clock, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ContentItem, SocialAccountSummary } from '@/types';

interface Props {
    accounts: SocialAccountSummary[];
    contents: ContentItem[];
    canPublish: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Multi-Platform Studio', href: '/social/accounts' },
    { title: 'Unified Composer', href: '/social/composer' },
];

export default function UnifiedComposerPage({ accounts, contents, canPublish }: Props) {
    const [selectedAccountIds, setSelectedAccountIds] = useState<number[]>(
        accounts.length > 0 ? [accounts[0].id] : [],
    );
    const [scheduledAt, setScheduledAt] = useState<string>('');

    const form = useForm({
        title: '',
        body: '',
        link_url: '',
        require_approval: false,
        targets: [] as Array<{ social_account_id: number; scheduled_at: string | null }>,
    });

    const toggleAccount = (id: number) => {
        setSelectedAccountIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id],
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (selectedAccountIds.length === 0) {
            return;
        }

        const targets = selectedAccountIds.map((id) => ({
            social_account_id: id,
            scheduled_at: scheduledAt ? new Date(scheduledAt).toISOString() : null,
        }));

        router.post(
            '/social/composer',
            {
                title: form.data.title || null,
                body: form.data.body,
                link_url: form.data.link_url || null,
                require_approval: form.data.require_approval,
                targets,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    setScheduledAt('');
                },
            },
        );
    };

    const handleApprove = (contentId: number) => {
        router.post(`/social/composer/${contentId}/approve`, {}, { preserveScroll: true });
    };

    const handleDelete = (contentId: number) => {
        router.delete(`/social/composer/${contentId}`, { preserveScroll: true });
    };

    const charCount = form.data.body.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Unified Composer" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Unified Multi-Platform Composer</h1>
                    <p className="text-sm text-muted-foreground">
                        Author once, validate per-channel capabilities, and dispatch idempotent jobs across all connected networks.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Compose & Schedule</CardTitle>
                            <CardDescription>
                                Select target accounts and optionally schedule a UTC publish time.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Target Accounts</Label>
                                    {accounts.length === 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            Connect an account in Social Accounts first.
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {accounts.map((acc) => {
                                                const active = selectedAccountIds.includes(acc.id);
                                                return (
                                                    <button
                                                        type="button"
                                                        key={acc.id}
                                                        onClick={() => toggleAccount(acc.id)}
                                                        className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition ${
                                                            active
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-border bg-background hover:bg-muted'
                                                        }`}
                                                    >
                                                        {active && <Check className="h-3 w-3" />}
                                                        <span className="capitalize">{acc.provider}</span>: {acc.display_name}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="composer-title">Title (Optional / YouTube)</Label>
                                    <Input
                                        id="composer-title"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        placeholder="Campaign headline or video title"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="composer-body">Post Body</Label>
                                        <span className="text-xs text-muted-foreground">{charCount} chars</span>
                                    </div>
                                    <textarea
                                        id="composer-body"
                                        rows={5}
                                        value={form.data.body}
                                        onChange={(e) => form.setData('body', e.target.value)}
                                        placeholder="Write your multi-platform update..."
                                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-link">Link URL (Optional)</Label>
                                        <Input
                                            id="composer-link"
                                            type="url"
                                            value={form.data.link_url}
                                            onChange={(e) => form.setData('link_url', e.target.value)}
                                            placeholder="https://example.com/launch"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-schedule">Schedule Time (Optional)</Label>
                                        <Input
                                            id="composer-schedule"
                                            type="datetime-local"
                                            value={scheduledAt}
                                            onChange={(e) => setScheduledAt(e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-2">
                                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <input
                                            type="checkbox"
                                            checked={form.data.require_approval}
                                            onChange={(e) => form.setData('require_approval', e.target.checked)}
                                        />
                                        Require Admin/Owner approval before dispatch
                                    </label>
                                    <Button type="submit" disabled={!canPublish || selectedAccountIds.length === 0}>
                                        {scheduledAt ? (
                                            <>
                                                <Clock className="mr-1.5 h-4 w-4" />
                                                Schedule Targets
                                            </>
                                        ) : (
                                            <>
                                                <Send className="mr-1.5 h-4 w-4" />
                                                Publish Now
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Selected Channel Limits</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {accounts
                                .filter((a) => selectedAccountIds.includes(a.id))
                                .map((acc) => (
                                    <div key={acc.id} className="rounded-md border p-3 text-xs">
                                        <div className="font-medium capitalize">
                                            {acc.provider} · {acc.display_name}
                                        </div>
                                        {acc.capabilities && (
                                            <div className="mt-1 text-muted-foreground">
                                                Limit: {acc.capabilities.max_text_length} chars
                                                {charCount > acc.capabilities.max_text_length && (
                                                    <span className="ml-2 font-semibold text-destructive">
                                                        (Exceeds limit!)
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent Multi-Platform Contents ({contents.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {contents.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No multi-platform posts created yet.</p>
                        ) : (
                            contents.map((item) => (
                                <div key={item.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{item.title ?? 'Untitled Post'}</span>
                                            <Badge variant="outline" className="capitalize">
                                                {item.status.replace('_', ' ')}
                                            </Badge>
                                        </div>
                                        {item.body && <p className="text-sm text-muted-foreground">{item.body}</p>}
                                        <div className="flex flex-wrap gap-2">
                                            {item.targets.map((target) => (
                                                <Badge key={target.id} variant="secondary" className="text-xs capitalize">
                                                    {target.provider}: {target.status}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {item.status === 'pending_approval' && (
                                            <Button size="sm" variant="outline" onClick={() => handleApprove(item.id)}>
                                                <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                                Approve
                                            </Button>
                                        )}
                                        <Button size="sm" variant="ghost" onClick={() => handleDelete(item.id)}>
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
