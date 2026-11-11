import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Calendar as CalendarIcon,
    ChevronLeft,
    ChevronRight,
    Clock,
    Edit3,
    ExternalLink,
    FileText,
    Image as ImageIcon,
    Link2,
    RotateCcw,
    Send,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import type { FacebookPageSummary, PostItem } from '@/types';

interface SchedulerProps {
    activePage: FacebookPageSummary | null;
    posts: PostItem[];
}

export default function PostSchedulerPage({ activePage, posts }: SchedulerProps) {
    const [activeTab, setActiveTab] = useState<'calendar' | 'queue' | 'drafts' | 'failures'>('calendar');
    const [editingPost, setEditingPost] = useState<PostItem | null>(null);
    const [currentMonth, setCurrentMonth] = useState(() => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1);
    });

    const defaultScheduleTime = useMemo(() => {
        const d = new Date(Date.now() + 3600 * 1000 * 24);
        return d.toISOString().slice(0, 16);
    }, []);

    const form = useForm({
        facebook_page_id: activePage?.id ?? 0,
        type: 'text' as 'text' | 'image' | 'link',
        content: '',
        media_url: '',
        link_url: '',
        link_title: '',
        scheduled_for: defaultScheduleTime,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
        save_as_draft: false,
        publish_now: false,
    });

    const startEditing = (post: PostItem) => {
        setEditingPost(post);
        form.setData({
            facebook_page_id: post.facebook_page_id,
            type: post.type,
            content: post.content,
            media_url: post.media_urls?.[0] ?? '',
            link_url: post.link_url ?? '',
            link_title: post.link_title ?? '',
            scheduled_for: post.schedule?.scheduled_for
                ? new Date(post.schedule.scheduled_for).toISOString().slice(0, 16)
                : defaultScheduleTime,
            timezone: post.schedule?.timezone ?? 'UTC',
            save_as_draft: post.status === 'draft',
            publish_now: false,
        });
    };

    const cancelEditing = () => {
        setEditingPost(null);
        form.reset();
    };

    const submitPost = (mode: 'schedule' | 'draft' | 'now') => {
        if (!activePage) {
            return;
        }

        const payload = {
            facebook_page_id: activePage.id,
            type: form.data.type,
            content: form.data.content,
            media_urls: form.data.type === 'image' && form.data.media_url ? [form.data.media_url] : [],
            link_url: form.data.type === 'link' ? form.data.link_url : null,
            link_title: form.data.type === 'link' ? form.data.link_title : null,
            scheduled_for: mode === 'draft' ? null : form.data.scheduled_for,
            timezone: form.data.timezone,
            save_as_draft: mode === 'draft',
            publish_now: mode === 'now',
        };

        if (editingPost) {
            router.put(`/facebook/scheduler/posts/${editingPost.id}`, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingPost(null);
                    form.reset();
                },
            });
        } else {
            router.post('/facebook/scheduler/posts', payload, {
                preserveScroll: true,
                onSuccess: () => form.reset('content', 'media_url', 'link_url', 'link_title'),
            });
        }
    };

    // Build calendar days for currentMonth
    const calendarDays = useMemo(() => {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        const firstDayOfWeek = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        const cells: Array<{ day: number | null; dateStr: string | null; items: PostItem[] }> = [];

        for (let i = 0; i < firstDayOfWeek; i++) {
            cells.push({ day: null, dateStr: null, items: [] });
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const cellDate = new Date(year, month, day);
            const isoPrefix = [
                cellDate.getFullYear(),
                String(cellDate.getMonth() + 1).padStart(2, '0'),
                String(cellDate.getDate()).padStart(2, '0'),
            ].join('-');

            const dayPosts = posts.filter((p) => {
                const targetIso = p.schedule?.scheduled_for ?? p.published_at;
                if (!targetIso) {
                    return false;
                }
                const d = new Date(targetIso);
                const dPrefix = [
                    d.getFullYear(),
                    String(d.getMonth() + 1).padStart(2, '0'),
                    String(d.getDate()).padStart(2, '0'),
                ].join('-');
                return dPrefix === isoPrefix;
            });

            cells.push({ day, dateStr: isoPrefix, items: dayPosts });
        }

        return cells;
    }, [currentMonth, posts]);

    const draftPosts = posts.filter((p) => p.status === 'draft');
    const failedPosts = posts.filter((p) => p.status === 'failed' || p.schedule?.status === 'failed');
    const scheduledAndPublishedPosts = posts.filter((p) => p.status !== 'draft');

    return (
        <>
            <Head title="Post Scheduler & Calendar" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Post Scheduler & Editorial Calendar
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Compose text, image, and link broadcasts for{' '}
                            <span className="font-medium text-foreground">{activePage?.name ?? 'your Page'}</span>.
                            Published via queued jobs with automatic exponential backoff.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-border bg-muted/40 p-1">
                        <Button
                            variant={activeTab === 'calendar' ? 'default' : 'ghost'}
                            size="sm"
                            className="h-8 text-xs"
                            onClick={() => setActiveTab('calendar')}
                        >
                            <CalendarIcon className="mr-1.5 size-3.5" />
                            Calendar View
                        </Button>
                        <Button
                            variant={activeTab === 'queue' ? 'default' : 'ghost'}
                            size="sm"
                            className="h-8 text-xs"
                            onClick={() => setActiveTab('queue')}
                        >
                            Queue & Published ({scheduledAndPublishedPosts.length})
                        </Button>
                        <Button
                            variant={activeTab === 'drafts' ? 'default' : 'ghost'}
                            size="sm"
                            className="h-8 text-xs"
                            onClick={() => setActiveTab('drafts')}
                        >
                            Drafts ({draftPosts.length})
                        </Button>
                        <Button
                            variant={activeTab === 'failures' ? 'destructive' : 'ghost'}
                            size="sm"
                            className="h-8 text-xs"
                            onClick={() => setActiveTab('failures')}
                        >
                            <AlertTriangle className="mr-1.5 size-3.5" />
                            Failure Logs ({failedPosts.length})
                        </Button>
                    </div>
                </div>

                {/* Top Split Layout: Post Composer + Live Facebook Preview & Calendar */}
                <div className="grid gap-6 xl:grid-cols-12">
                    {/* Left 5 Cols: Post Composer & Live Feed Preview */}
                    <div className="space-y-6 xl:col-span-5">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        {editingPost ? `Edit Post #${editingPost.id}` : 'Compose Page Broadcast'}
                                    </CardTitle>
                                    {editingPost && (
                                        <Button variant="ghost" size="sm" onClick={cancelEditing}>
                                            Cancel Edit
                                        </Button>
                                    )}
                                </div>
                                <CardDescription>
                                    Select post format (`text`, `image`, or `link`) and target publish time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Post Type Selector */}
                                <div className="grid grid-cols-3 gap-2">
                                    {(
                                        [
                                            { id: 'text', label: 'Text Post', icon: FileText },
                                            { id: 'image', label: 'Image Post', icon: ImageIcon },
                                            { id: 'link', label: 'Link Card', icon: Link2 },
                                        ] as const
                                    ).map((tab) => {
                                        const Icon = tab.icon;
                                        const selected = form.data.type === tab.id;
                                        return (
                                            <button
                                                key={tab.id}
                                                type="button"
                                                onClick={() => form.setData('type', tab.id)}
                                                className={`flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium transition ${
                                                    selected
                                                        ? 'border-blue-600 bg-blue-600/10 text-blue-600 dark:text-blue-400'
                                                        : 'border-border hover:bg-muted'
                                                }`}
                                            >
                                                <Icon className="size-3.5" />
                                                {tab.label}
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="post-content">Post Copy / Caption</Label>
                                    <textarea
                                        id="post-content"
                                        rows={4}
                                        value={form.data.content}
                                        onChange={(e) => form.setData('content', e.target.value)}
                                        placeholder="Write your Facebook Page announcement..."
                                        className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>

                                {form.data.type === 'image' && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="media_url">High-Resolution Image URL</Label>
                                        <Input
                                            id="media_url"
                                            type="url"
                                            placeholder="https://images.unsplash.com/..."
                                            value={form.data.media_url}
                                            onChange={(e) => form.setData('media_url', e.target.value)}
                                        />
                                    </div>
                                )}

                                {form.data.type === 'link' && (
                                    <div className="space-y-3">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="link_url">Destination URL</Label>
                                            <Input
                                                id="link_url"
                                                type="url"
                                                placeholder="https://example.com/article"
                                                value={form.data.link_url}
                                                onChange={(e) => form.setData('link_url', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="link_title">Link Headline (Optional)</Label>
                                            <Input
                                                id="link_title"
                                                placeholder="Headline preview"
                                                value={form.data.link_title}
                                                onChange={(e) => form.setData('link_title', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-1.5">
                                    <Label htmlFor="scheduled_for">Schedule Date & Time ({form.data.timezone})</Label>
                                    <Input
                                        id="scheduled_for"
                                        type="datetime-local"
                                        value={form.data.scheduled_for}
                                        onChange={(e) => form.setData('scheduled_for', e.target.value)}
                                    />
                                </div>

                                {/* Live Facebook Feed Post Preview */}
                                <div className="rounded-xl border border-border bg-muted/30 p-3.5">
                                    <div className="mb-2 flex items-center justify-between text-[11px] font-medium text-muted-foreground">
                                        <span>Live Facebook Page Feed Preview</span>
                                        <span className="font-mono uppercase">{form.data.type}</span>
                                    </div>
                                    <div className="rounded-lg border border-border bg-card p-3 shadow-2xs">
                                        <div className="flex items-center gap-2.5">
                                            <div className="flex size-8 items-center justify-center rounded-full bg-[#1877F2] text-xs font-bold text-white">
                                                {activePage?.name.slice(0, 1) ?? 'P'}
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold">
                                                    {activePage?.name ?? 'Connected Facebook Page'}
                                                </p>
                                                <p className="text-[10px] text-muted-foreground">
                                                    Scheduled · 🌐 Public
                                                </p>
                                            </div>
                                        </div>
                                        <p className="mt-2.5 text-xs whitespace-pre-line text-foreground">
                                            {form.data.content || 'Your post caption will appear here...'}
                                        </p>
                                        {form.data.type === 'image' && form.data.media_url && (
                                            <div className="mt-2.5 overflow-hidden rounded-lg border border-border">
                                                <img
                                                    src={form.data.media_url}
                                                    alt="Post preview"
                                                    className="max-h-48 w-full object-cover"
                                                />
                                            </div>
                                        )}
                                        {form.data.type === 'link' && form.data.link_url && (
                                            <div className="mt-2.5 rounded-lg border border-border bg-muted/50 p-2.5">
                                                <p className="truncate font-mono text-[10px] text-muted-foreground uppercase">
                                                    {form.data.link_url}
                                                </p>
                                                <p className="mt-0.5 text-xs font-semibold">
                                                    {form.data.link_title || form.data.link_url}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 pt-1">
                                    <Button
                                        type="button"
                                        className="flex-1 bg-[#1877F2] text-white hover:bg-[#166FE5]"
                                        disabled={!form.data.content || !activePage}
                                        onClick={() => submitPost('schedule')}
                                    >
                                        <Clock className="mr-1.5 size-4" />
                                        {editingPost ? 'Update Schedule' : 'Schedule Broadcast'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!form.data.content || !activePage}
                                        onClick={() => submitPost('draft')}
                                    >
                                        Save Draft
                                    </Button>
                                    {!editingPost && (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            disabled={!form.data.content || !activePage}
                                            onClick={() => submitPost('now')}
                                        >
                                            <Send className="mr-1.5 size-3.5" />
                                            Queue Now
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right 7 Cols: Calendar View / Queue / Drafts / Failure Logs */}
                    <div className="xl:col-span-7">
                        {activeTab === 'calendar' && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-3">
                                    <div>
                                        <CardTitle className="text-base">
                                            {currentMonth.toLocaleString('default', {
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                        </CardTitle>
                                        <CardDescription>
                                            Click any scheduled item on the calendar to inspect or edit
                                        </CardDescription>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setCurrentMonth(
                                                    new Date(
                                                        currentMonth.getFullYear(),
                                                        currentMonth.getMonth() - 1,
                                                        1,
                                                    ),
                                                )
                                            }
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                const now = new Date();
                                                setCurrentMonth(new Date(now.getFullYear(), now.getMonth(), 1));
                                            }}
                                        >
                                            Today
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setCurrentMonth(
                                                    new Date(
                                                        currentMonth.getFullYear(),
                                                        currentMonth.getMonth() + 1,
                                                        1,
                                                    ),
                                                )
                                            }
                                        >
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted-foreground">
                                        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                                            <div key={d} className="py-1.5">
                                                {d}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-1 grid grid-cols-7 gap-1.5">
                                        {calendarDays.map((cell, idx) => (
                                            <div
                                                key={cell.dateStr ?? `empty-${idx}`}
                                                className={`min-h-24 rounded-lg border p-1.5 text-xs ${
                                                    cell.day === null
                                                        ? 'border-transparent bg-muted/20'
                                                        : 'border-border bg-card'
                                                }`}
                                            >
                                                {cell.day !== null && (
                                                    <>
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-mono text-[11px] font-medium text-muted-foreground">
                                                                {cell.day}
                                                            </span>
                                                            {cell.items.length > 0 && (
                                                                <span className="rounded-full bg-blue-500/10 px-1.5 text-[10px] font-semibold text-blue-600 dark:text-blue-400">
                                                                    {cell.items.length}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="mt-1 space-y-1">
                                                            {cell.items.map((item) => (
                                                                <button
                                                                    key={item.id}
                                                                    type="button"
                                                                    onClick={() => startEditing(item)}
                                                                    className={`block w-full truncate rounded px-1.5 py-0.5 text-left text-[10px] font-medium ${
                                                                        item.status === 'published'
                                                                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                                            : item.status === 'failed'
                                                                              ? 'bg-red-500/15 text-red-700 dark:text-red-300'
                                                                              : 'bg-blue-500/15 text-blue-700 dark:text-blue-300'
                                                                    }`}
                                                                >
                                                                    {item.type.toUpperCase()}: {item.content}
                                                                </button>
                                                            ))}
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {(activeTab === 'queue' || activeTab === 'drafts') && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {activeTab === 'drafts' ? 'Saved Post Drafts' : 'Scheduled & Published Posts'}
                                    </CardTitle>
                                    <CardDescription>
                                        Edit, reschedule, or delete posts at any time before publishing.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {(activeTab === 'drafts' ? draftPosts : scheduledAndPublishedPosts).map((post) => (
                                        <div
                                            key={post.id}
                                            className="flex flex-col justify-between gap-3 rounded-lg border border-border p-3.5 sm:flex-row sm:items-center"
                                        >
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                                        {post.type}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            post.status === 'published'
                                                                ? 'default'
                                                                : post.status === 'failed'
                                                                  ? 'destructive'
                                                                  : 'secondary'
                                                        }
                                                        className="font-mono text-[10px] uppercase"
                                                    >
                                                        {post.status}
                                                    </Badge>
                                                    {post.schedule?.scheduled_for && (
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            Scheduled: {new Date(post.schedule.scheduled_for).toLocaleString()}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-sm text-foreground">{post.content}</p>
                                                {post.permalink_url && (
                                                    <a
                                                        href={post.permalink_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        View on Facebook ({post.facebook_post_id})
                                                        <ExternalLink className="size-3" />
                                                    </a>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1.5">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => startEditing(post)}
                                                >
                                                    <Edit3 className="mr-1 size-3.5" />
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-red-600 hover:bg-red-500/10"
                                                    onClick={() => router.delete(`/facebook/scheduler/posts/${post.id}`)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {activeTab === 'failures' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base text-red-600 dark:text-red-400">
                                        <AlertTriangle className="size-4" />
                                        Graph API Publish Failure Log & Retry Backoff History
                                    </CardTitle>
                                    <CardDescription>
                                        Inspect structured attempt logs, Meta Graph error codes (`#190`, `#4`, `#200`), and trigger manual retries.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {failedPosts.length === 0 ? (
                                        <p className="py-8 text-center text-sm text-muted-foreground">
                                            Zero failed scheduled posts. All broadcasts are healthy.
                                        </p>
                                    ) : (
                                        failedPosts.map((post) => (
                                            <div
                                                key={post.id}
                                                className="space-y-3 rounded-xl border border-red-500/30 bg-red-500/5 p-4"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="destructive" className="font-mono text-[10px]">
                                                                Graph Error #{post.schedule?.error_code ?? 4}
                                                            </Badge>
                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                Attempts: {post.schedule?.attempts ?? 1}/
                                                                {post.schedule?.max_attempts ?? 4}
                                                            </span>
                                                        </div>
                                                        <p className="mt-1.5 text-sm font-medium">{post.content}</p>
                                                        <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                                            {post.last_error ?? post.schedule?.error_message}
                                                        </p>
                                                    </div>

                                                    {post.schedule && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/facebook/scheduler/schedules/${post.schedule?.id}/retry`,
                                                                )
                                                            }
                                                        >
                                                            <RotateCcw className="mr-1.5 size-3.5" />
                                                            Retry Broadcast Now
                                                        </Button>
                                                    )}
                                                </div>

                                                {post.schedule?.failure_log && post.schedule.failure_log.length > 0 && (
                                                    <div className="space-y-1.5 rounded-lg border border-border bg-card p-3">
                                                        <p className="font-mono text-[11px] font-semibold text-muted-foreground uppercase">
                                                            Exponential Backoff Attempt Trace
                                                        </p>
                                                        {post.schedule.failure_log.map((entry, i) => (
                                                            <div
                                                                key={i}
                                                                className="border-t border-border pt-1.5 text-xs first:border-t-0 first:pt-0"
                                                            >
                                                                <div className="flex items-center justify-between font-mono text-[11px]">
                                                                    <span className="font-semibold">
                                                                        Attempt #{entry.attempt} · HTTP {entry.http_status} (Code{' '}
                                                                        {entry.graph_code}
                                                                        {entry.graph_subcode ? `/${entry.graph_subcode}` : ''})
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        {new Date(entry.timestamp).toLocaleTimeString()}
                                                                    </span>
                                                                </div>
                                                                <p className="text-muted-foreground">{entry.message}</p>
                                                                {entry.remediation && (
                                                                    <p className="text-[11px] text-amber-600 dark:text-amber-400">
                                                                        Remediation: {entry.remediation}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
