import { Head, router, useForm } from '@inertiajs/react';
import {
    Clock,
    EyeOff,
    MessageSquareReply,
    Play,
    Plus,
    ShieldAlert,
    Sparkles,
    Trash2,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AutoReplyRuleItem, FacebookPageSummary, MessageItem } from '@/types';

interface AutoRepliesProps {
    activePage: FacebookPageSummary | null;
    rules: AutoReplyRuleItem[];
    messages: MessageItem[];
}

export default function AutoRepliesPage({ activePage, rules, messages }: AutoRepliesProps) {
    const [showCreateForm, setShowCreateForm] = useState(false);

    const ruleForm = useForm({
        facebook_page_id: activePage?.id ?? 0,
        name: '',
        channel: 'both' as 'messenger' | 'comment' | 'both',
        trigger_type: 'keyword' as 'keyword' | 'exact' | 'contains' | 'regex' | 'fallback',
        keywords_csv: 'price, cost, quote',
        banned_words_csv: '',
        reply_template:
            'Hi {{sender_name}}! Thanks for reaching out to {{page_name}}. Here are our latest pricing details: https://example.com/pricing',
        moderation_action: 'none' as 'none' | 'hide',
        respect_24h_window: true,
        cooldown_seconds: 300,
        priority: 50,
        is_active: true,
    });

    const simForm = useForm({
        facebook_page_id: activePage?.id ?? 0,
        channel: 'messenger' as 'messenger' | 'comment',
        sender_name: 'Alex Rivera',
        message_text: 'Hi! Can you send me the price list for your new collection?',
        hours_since_last_interaction: 1,
    });

    const handleCreateRule = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activePage) {
            return;
        }

        const keywords = ruleForm.data.keywords_csv
            .split(',')
            .map((w) => w.trim())
            .filter(Boolean);

        const bannedWords = ruleForm.data.banned_words_csv
            .split(',')
            .map((w) => w.trim())
            .filter(Boolean);

        router.post(
            '/facebook/auto-replies',
            {
                facebook_page_id: activePage.id,
                name: ruleForm.data.name,
                channel: ruleForm.data.channel,
                trigger_type: ruleForm.data.trigger_type,
                keywords,
                banned_words: bannedWords,
                reply_template: ruleForm.data.reply_template,
                moderation_action: ruleForm.data.moderation_action,
                respect_24h_window: ruleForm.data.respect_24h_window,
                cooldown_seconds: ruleForm.data.cooldown_seconds,
                priority: ruleForm.data.priority,
                is_active: true,
            },
            {
                onSuccess: () => {
                    setShowCreateForm(false);
                    ruleForm.reset('name');
                },
            },
        );
    };

    const handleSimulate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activePage) {
            return;
        }

        router.post(
            '/facebook/auto-replies/simulate',
            {
                ...simForm.data,
                facebook_page_id: activePage.id,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Auto-Reply Studio & Comment Moderation" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Messenger & Comment Auto-Reply Studio
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Keyword rules, fallback replies, banned-word comment hiding, and strict 24-hour standard messaging window compliance.
                        </p>
                    </div>
                    <Button
                        size="sm"
                        className="bg-[#1877F2] text-white hover:bg-[#166FE5]"
                        onClick={() => setShowCreateForm(!showCreateForm)}
                    >
                        <Plus className="mr-1.5 size-4" />
                        {showCreateForm ? 'Close Rule Builder' : 'New Auto-Reply Rule'}
                    </Button>
                </div>

                {/* Create Rule Form */}
                {showCreateForm && (
                    <Card className="border-blue-500/40">
                        <CardHeader>
                            <CardTitle className="text-base">Create Keyword or Moderation Rule</CardTitle>
                            <CardDescription>
                                Supports dynamic placeholders <code>{'{{sender_name}}'}</code> and{' '}
                                <code>{'{{page_name}}'}</code>.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleCreateRule} className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="rule-name">Rule Name</Label>
                                    <Input
                                        id="rule-name"
                                        placeholder="e.g. Pricing & Catalog Canned Reply"
                                        value={ruleForm.data.name}
                                        onChange={(e) => ruleForm.setData('name', e.target.value)}
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="channel">Channel</Label>
                                        <select
                                            id="channel"
                                            value={ruleForm.data.channel}
                                            onChange={(e) =>
                                                ruleForm.setData(
                                                    'channel',
                                                    e.target.value as 'messenger' | 'comment' | 'both',
                                                )
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs"
                                        >
                                            <option value="both">Both</option>
                                            <option value="messenger">Messenger Only</option>
                                            <option value="comment">Comments Only</option>
                                        </select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="trigger_type">Trigger Type</Label>
                                        <select
                                            id="trigger_type"
                                            value={ruleForm.data.trigger_type}
                                            onChange={(e) =>
                                                ruleForm.setData(
                                                    'trigger_type',
                                                    e.target.value as
                                                        | 'keyword'
                                                        | 'exact'
                                                        | 'contains'
                                                        | 'regex'
                                                        | 'fallback',
                                                )
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs"
                                        >
                                            <option value="keyword">Keyword Match</option>
                                            <option value="exact">Exact Match</option>
                                            <option value="regex">Regex Pattern</option>
                                            <option value="fallback">Fallback Default</option>
                                        </select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="moderation_action">Comment Moderation</Label>
                                        <select
                                            id="moderation_action"
                                            value={ruleForm.data.moderation_action}
                                            onChange={(e) =>
                                                ruleForm.setData(
                                                    'moderation_action',
                                                    e.target.value as 'none' | 'hide',
                                                )
                                            }
                                            className="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs"
                                        >
                                            <option value="none">No Moderation</option>
                                            <option value="hide">Hide Banned Words</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="keywords_csv">Trigger Keywords (comma-separated)</Label>
                                    <Input
                                        id="keywords_csv"
                                        placeholder="price, cost, how much, quote"
                                        value={ruleForm.data.keywords_csv}
                                        onChange={(e) => ruleForm.setData('keywords_csv', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="banned_words_csv">
                                        Banned Words to Auto-Hide in Comments (optional)
                                    </Label>
                                    <Input
                                        id="banned_words_csv"
                                        placeholder="crypto giveaway, whatsapp me, spam link"
                                        value={ruleForm.data.banned_words_csv}
                                        onChange={(e) => ruleForm.setData('banned_words_csv', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-1.5 md:col-span-2">
                                    <Label htmlFor="reply_template">Canned Reply Template</Label>
                                    <textarea
                                        id="reply_template"
                                        rows={3}
                                        value={ruleForm.data.reply_template}
                                        onChange={(e) => ruleForm.setData('reply_template', e.target.value)}
                                        className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                </div>

                                <div className="flex items-center justify-between md:col-span-2">
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            checked={ruleForm.data.respect_24h_window}
                                            onChange={(e) =>
                                                ruleForm.setData('respect_24h_window', e.target.checked)
                                            }
                                        />
                                        Enforce Meta 24-Hour Standard Messaging Window (`messaging_type: RESPONSE`)
                                    </label>
                                    <Button type="submit">Save Auto-Reply Rule</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 xl:grid-cols-12">
                    {/* Left 7 Cols: Active Auto-Reply & Moderation Rules */}
                    <div className="space-y-4 xl:col-span-7">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Configured Rules ({rules.length})
                                </CardTitle>
                                <CardDescription>
                                    Evaluated in priority order when a verified webhook event arrives.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {rules.map((rule) => (
                                    <div
                                        key={rule.id}
                                        className={`space-y-2.5 rounded-xl border p-4 transition ${
                                            rule.is_active
                                                ? 'border-border bg-card'
                                                : 'border-dashed border-border bg-muted/20 opacity-70'
                                        }`}
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    P{rule.priority}
                                                </span>
                                                <h3 className="text-sm font-semibold">{rule.name}</h3>
                                                <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                    {rule.channel}
                                                </Badge>
                                                <Badge variant="secondary" className="font-mono text-[10px] uppercase">
                                                    {rule.trigger_type}
                                                </Badge>
                                                {rule.moderation_action === 'hide' && (
                                                    <Badge variant="destructive" className="font-mono text-[10px]">
                                                        <EyeOff className="mr-1 size-3" />
                                                        Auto-Hide Comment
                                                    </Badge>
                                                )}
                                            </div>

                                            <div className="flex items-center gap-1.5">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 text-xs"
                                                    onClick={() =>
                                                        router.post(`/facebook/auto-replies/${rule.id}/toggle`)
                                                    }
                                                >
                                                    {rule.is_active ? 'Pause' : 'Enable'}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 text-red-600 hover:bg-red-500/10"
                                                    onClick={() =>
                                                        router.delete(`/facebook/auto-replies/${rule.id}`)
                                                    }
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </div>
                                        </div>

                                        {rule.keywords && rule.keywords.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="text-[11px] text-muted-foreground">Keywords:</span>
                                                {rule.keywords.map((kw) => (
                                                    <span
                                                        key={kw}
                                                        className="rounded bg-blue-500/10 px-2 py-0.5 font-mono text-[11px] text-blue-600 dark:text-blue-400"
                                                    >
                                                        &ldquo;{kw}&rdquo;
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        {rule.banned_words && rule.banned_words.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="text-[11px] text-red-500">Banned Words:</span>
                                                {rule.banned_words.map((bw) => (
                                                    <span
                                                        key={bw}
                                                        className="rounded bg-red-500/10 px-2 py-0.5 font-mono text-[11px] text-red-600 dark:text-red-400"
                                                    >
                                                        &ldquo;{bw}&rdquo;
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        <p className="rounded-lg bg-muted/50 p-2.5 text-xs text-foreground">
                                            {rule.reply_template}
                                        </p>

                                        <div className="flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                                            <span>
                                                Triggered <strong className="font-mono">{rule.match_count}</strong>{' '}
                                                times · Cooldown {rule.cooldown_seconds}s
                                            </span>
                                            {rule.respect_24h_window && (
                                                <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                    <Clock className="size-3" />
                                                    24h Window Policy Active
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right 5 Cols: Interactive Simulator + Message/Comment Log */}
                    <div className="space-y-6 xl:col-span-5">
                        {/* Live Webhook & 24h Window Simulator */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Sparkles className="size-4 text-amber-500" />
                                    Webhook & 24-Hour Window Simulator
                                </CardTitle>
                                <CardDescription>
                                    Test keyword matching, banned-word hiding, or 24-hour window blocking (`&gt; 24h` since last interaction).
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSimulate} className="space-y-3">
                                    <div className="grid grid-cols-2 gap-2.5">
                                        <div className="space-y-1">
                                            <Label htmlFor="sim-channel" className="text-xs">
                                                Inbound Channel
                                            </Label>
                                            <select
                                                id="sim-channel"
                                                value={simForm.data.channel}
                                                onChange={(e) =>
                                                    simForm.setData(
                                                        'channel',
                                                        e.target.value as 'messenger' | 'comment',
                                                    )
                                                }
                                                className="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs"
                                            >
                                                <option value="messenger">Messenger DM</option>
                                                <option value="comment">Page Feed Comment</option>
                                            </select>
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="sim-hours" className="text-xs">
                                                Hours Since User Message
                                            </Label>
                                            <Input
                                                id="sim-hours"
                                                type="number"
                                                min={0}
                                                max={168}
                                                value={simForm.data.hours_since_last_interaction}
                                                onChange={(e) =>
                                                    simForm.setData(
                                                        'hours_since_last_interaction',
                                                        Number(e.target.value),
                                                    )
                                                }
                                                className="h-9 font-mono text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1">
                                        <Label htmlFor="sim-text" className="text-xs">
                                            Inbound Message / Comment Text
                                        </Label>
                                        <Input
                                            id="sim-text"
                                            value={simForm.data.message_text}
                                            onChange={(e) => simForm.setData('message_text', e.target.value)}
                                        />
                                    </div>

                                    {simForm.data.channel === 'messenger' &&
                                        simForm.data.hours_since_last_interaction > 24 && (
                                            <p className="flex items-center gap-1.5 rounded-md bg-amber-500/10 px-2.5 py-1.5 text-xs text-amber-600 dark:text-amber-400">
                                                <ShieldAlert className="size-4 shrink-0" />
                                                Simulating interaction {simForm.data.hours_since_last_interaction}h ago:
                                                Auto-reply will be blocked by Meta&apos;s 24h window policy!
                                            </p>
                                        )}

                                    <Button type="submit" size="sm" className="w-full" disabled={!activePage}>
                                        <Play className="mr-1.5 size-3.5" />
                                        Simulate Inbound Event
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Recent Processed Messages & Comments */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MessageSquareReply className="size-4 text-blue-600 dark:text-blue-400" />
                                    Inbound & Auto-Reply Log
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="max-h-[460px] space-y-2.5 overflow-y-auto">
                                {messages.map((msg) => (
                                    <div
                                        key={msg.id}
                                        className="space-y-1.5 rounded-lg border border-border p-3 text-xs"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-1.5">
                                                <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                    {msg.channel}
                                                </Badge>
                                                <span className="font-semibold">{msg.sender_name}</span>
                                            </div>
                                            <Badge
                                                variant={
                                                    msg.status === 'replied'
                                                        ? 'default'
                                                        : msg.status === 'moderated'
                                                          ? 'destructive'
                                                          : 'secondary'
                                                }
                                                className="font-mono text-[10px]"
                                            >
                                                {msg.status}
                                            </Badge>
                                        </div>

                                        <p className="text-foreground">&ldquo;{msg.message_text}&rdquo;</p>

                                        {msg.reply_text && (
                                            <div className="rounded bg-blue-500/10 px-2.5 py-1.5 text-[11px]">
                                                <span className="font-semibold text-blue-600 dark:text-blue-400">
                                                    Reply ({msg.rule_name ?? 'Rule'}):{' '}
                                                </span>
                                                {msg.reply_text}
                                            </div>
                                        )}

                                        {msg.is_hidden && (
                                            <p className="flex items-center gap-1 text-[11px] font-medium text-red-600 dark:text-red-400">
                                                <EyeOff className="size-3" />
                                                Comment hidden on Facebook Page via Graph API moderation
                                            </p>
                                        )}

                                        {msg.error_message && (
                                            <p className="text-[11px] text-amber-600 dark:text-amber-400">
                                                {msg.error_message}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
