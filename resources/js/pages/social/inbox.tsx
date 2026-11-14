import { Head, router, useForm } from '@inertiajs/react';
import { EyeOff, MessageSquare, Send } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface ConversationItem {
    id: number;
    provider: string;
    account_name: string;
    participant_name: string;
    status: string;
    unread_count: number;
    last_message_at: string | null;
    messages: Array<{
        id: number;
        direction: string;
        sender_name: string | null;
        body: string;
        sent_at: string | null;
    }>;
}

interface CommentItem {
    id: number;
    provider: string;
    account_name: string;
    external_post_id: string;
    author_name: string | null;
    body: string;
    is_hidden: boolean;
    published_at: string | null;
}

interface Props {
    conversations: ConversationItem[];
    comments: CommentItem[];
}

export default function UnifiedInboxPage({ conversations, comments }: Props) {
    const [activeConvId, setActiveConvId] = useState<number | null>(conversations[0]?.id ?? null);
    const activeConv = conversations.find((c) => c.id === activeConvId) ?? conversations[0] ?? null;
    const replyForm = useForm({ body: '' });

    const handleReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeConv || !replyForm.data.body.trim()) {
            return;
        }
        replyForm.post(`/social/inbox/conversations/${activeConv.id}/reply`, {
            preserveScroll: true,
            onSuccess: () => replyForm.reset('body'),
        });
    };

    const handleToggleHide = (commentId: number) => {
        router.post(`/social/inbox/comments/${commentId}/toggle-hide`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Unified Inbox" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Unified Inbox & Comment Moderation</h1>
                    <p className="text-sm text-muted-foreground">
                        Respond to direct messages and moderate comments across connected social channels.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Conversations ({conversations.length})</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {conversations.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">No conversations yet.</p>
                            ) : (
                                conversations.map((conv) => (
                                    <button
                                        key={conv.id}
                                        type="button"
                                        onClick={() => setActiveConvId(conv.id)}
                                        className={`flex w-full items-start justify-between p-4 text-left transition hover:bg-muted/50 ${
                                            activeConv?.id === conv.id ? 'bg-muted' : ''
                                        }`}
                                    >
                                        <div>
                                            <div className="font-medium">{conv.participant_name}</div>
                                            <div className="text-xs capitalize text-muted-foreground">
                                                {conv.provider} · {conv.account_name}
                                            </div>
                                        </div>
                                        {conv.unread_count > 0 && <Badge>{conv.unread_count}</Badge>}
                                    </button>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <MessageSquare className="h-4 w-4" />
                                {activeConv ? `${activeConv.participant_name} (${activeConv.provider})` : 'Select a conversation'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {activeConv ? (
                                <>
                                    <div className="max-h-72 space-y-3 overflow-y-auto rounded-md border p-3">
                                        {activeConv.messages.map((msg) => (
                                            <div
                                                key={msg.id}
                                                className={`rounded-lg p-3 text-sm ${
                                                    msg.direction === 'outbound'
                                                        ? 'ml-8 bg-primary text-primary-foreground'
                                                        : 'mr-8 bg-muted'
                                                }`}
                                            >
                                                <div className="mb-1 text-xs opacity-75">{msg.sender_name ?? msg.direction}</div>
                                                <div>{msg.body}</div>
                                            </div>
                                        ))}
                                    </div>
                                    <form onSubmit={handleReply} className="flex gap-2">
                                        <Input
                                            value={replyForm.data.body}
                                            onChange={(e) => replyForm.setData('body', e.target.value)}
                                            placeholder="Type a reply..."
                                        />
                                        <Button type="submit" disabled={replyForm.processing}>
                                            <Send className="mr-1.5 h-4 w-4" />
                                            Send
                                        </Button>
                                    </form>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">Select a thread from the left to view messages.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent Comments ({comments.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {comments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No comments ingested yet.</p>
                        ) : (
                            comments.map((comment) => (
                                <div key={comment.id} className="flex items-center justify-between gap-4 py-3">
                                    <div>
                                        <div className="flex items-center gap-2 text-sm font-medium">
                                            <span>{comment.author_name ?? 'User'}</span>
                                            <Badge variant="outline" className="capitalize">
                                                {comment.provider}
                                            </Badge>
                                            {comment.is_hidden && <Badge variant="destructive">Hidden</Badge>}
                                        </div>
                                        <p className="text-sm text-muted-foreground">{comment.body}</p>
                                    </div>
                                    <Button size="sm" variant="outline" onClick={() => handleToggleHide(comment.id)}>
                                        <EyeOff className="mr-1.5 h-3.5 w-3.5" />
                                        {comment.is_hidden ? 'Unhide' : 'Hide'}
                                    </Button>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
