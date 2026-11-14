import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Plus, RefreshCw, ShieldCheck, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProviderCapabilities, SocialAccountSummary } from '@/types';

interface ProviderCatalogItem {
    key: string;
    label: string;
    configured: boolean;
    capabilities: ProviderCapabilities;
}

interface Props {
    accounts: SocialAccountSummary[];
    providerCatalog: ProviderCatalogItem[];
    canManageAccounts: boolean;
}

export default function SocialAccountsPage({ accounts, providerCatalog, canManageAccounts }: Props) {
    const sandboxForm = useForm({
        provider: 'threads',
        display_name: '',
        handle: '',
    });

    const handleConnectSandbox = (e: React.FormEvent) => {
        e.preventDefault();
        sandboxForm.post('/social/accounts/sandbox', {
            preserveScroll: true,
            onSuccess: () => sandboxForm.reset('display_name', 'handle'),
        });
    };

    const handleDisconnect = (accountId: number) => {
        router.delete(`/social/accounts/${accountId}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Social Accounts" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Multi-Platform Accounts</h1>
                        <p className="text-sm text-muted-foreground">
                            Connect Facebook, Instagram, Threads, LinkedIn, and YouTube accounts with encrypted token vault isolation.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {providerCatalog
                        .filter((p) => p.key !== 'fake')
                        .map((provider) => (
                            <Card key={provider.key}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">{provider.label}</CardTitle>
                                        <Badge variant={provider.configured ? 'default' : 'secondary'}>
                                            {provider.configured ? 'OAuth Ready' : 'Env Pending'}
                                        </Badge>
                                    </div>
                                    <CardDescription className="text-xs">
                                        Max text: {provider.capabilities.max_text_length} chars
                                        {provider.capabilities.supports_analytics ? ' · Analytics' : ''}
                                        {provider.capabilities.supports_comments ? ' · Comments' : ''}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between gap-2">
                                    <div className="flex flex-wrap gap-1">
                                        {provider.capabilities.text && <Badge variant="outline">Text</Badge>}
                                        {provider.capabilities.images && <Badge variant="outline">Images</Badge>}
                                        {provider.capabilities.video && <Badge variant="outline">Video</Badge>}
                                    </div>
                                    {canManageAccounts && (
                                        <Button size="sm" asChild>
                                            <a href={`/social/accounts/oauth/${provider.key}/redirect`}>
                                                <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                                                Connect
                                            </a>
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                </div>

                {canManageAccounts && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Plus className="h-4 w-4" />
                                Connect Sandbox / Demo Channel
                            </CardTitle>
                            <CardDescription>
                                Quickly provision a sandbox channel for local multi-platform testing without live OAuth credentials.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleConnectSandbox} className="grid gap-4 sm:grid-cols-4 sm:items-end">
                                <div className="space-y-1.5">
                                    <Label htmlFor="sandbox-provider">Provider</Label>
                                    <select
                                        id="sandbox-provider"
                                        value={sandboxForm.data.provider}
                                        onChange={(e) => sandboxForm.setData('provider', e.target.value)}
                                        className="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm"
                                    >
                                        <option value="facebook">Facebook</option>
                                        <option value="instagram">Instagram</option>
                                        <option value="threads">Threads</option>
                                        <option value="linkedin">LinkedIn</option>
                                        <option value="youtube">YouTube</option>
                                        <option value="fake">Fake (Test Driver)</option>
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="sandbox-name">Display Name</Label>
                                    <Input
                                        id="sandbox-name"
                                        value={sandboxForm.data.display_name}
                                        onChange={(e) => sandboxForm.setData('display_name', e.target.value)}
                                        placeholder="Acme Studio Channel"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="sandbox-handle">Handle</Label>
                                    <Input
                                        id="sandbox-handle"
                                        value={sandboxForm.data.handle}
                                        onChange={(e) => sandboxForm.setData('handle', e.target.value)}
                                        placeholder="@acmestudio"
                                    />
                                </div>
                                <Button type="submit" disabled={sandboxForm.processing}>
                                    Add Sandbox Account
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck className="h-4 w-4 text-emerald-500" />
                            Connected Workspace Accounts ({accounts.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {accounts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No multi-platform accounts connected yet.</p>
                        ) : (
                            <div className="divide-y">
                                {accounts.map((account) => (
                                    <div key={account.id} className="flex flex-col justify-between gap-3 py-3 sm:flex-row sm:items-center">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{account.display_name}</span>
                                                <Badge variant="outline" className="capitalize">
                                                    {account.provider_label}
                                                </Badge>
                                                <Badge variant={account.status === 'active' ? 'default' : 'destructive'}>
                                                    {account.reauth_required ? 'Reauth Required' : account.status}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {account.handle ?? account.external_id} · Kind: {account.kind}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {account.status === 'active' && !account.reauth_required ? (
                                                <span className="inline-flex items-center gap-1 text-xs text-emerald-600">
                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                    Token Vaulted
                                                </span>
                                            ) : (
                                                <Button size="sm" variant="outline" asChild>
                                                    <a href={`/social/accounts/oauth/${account.provider}/redirect`}>
                                                        <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                                                        Reconnect
                                                    </a>
                                                </Button>
                                            )}
                                            {canManageAccounts && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => handleDisconnect(account.id)}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
