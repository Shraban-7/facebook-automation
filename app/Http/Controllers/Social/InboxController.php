<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\ReplyToConversation;
use App\Contracts\Social\SupportsComments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Social\ReplyConversationRequest;
use App\Models\Comment;
use App\Models\Conversation;
use App\Services\Social\ProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();

        $conversations = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->with(['socialAccount', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get()
            ->map(fn (Conversation $conv): array => [
                'id' => $conv->id,
                'provider' => $conv->socialAccount->provider,
                'account_name' => $conv->socialAccount->name,
                'participant_name' => $conv->participant_name ?? 'Contact',
                'status' => $conv->status,
                'unread_count' => $conv->unread_count,
                'last_message_at' => $conv->last_message_at?->toIso8601String(),
                'messages' => $conv->messages->map(fn ($msg): array => [
                    'id' => $msg->id,
                    'direction' => $msg->direction,
                    'sender_name' => $msg->direction === 'inbound' ? ($conv->participant_name ?? 'Contact') : 'Agent',
                    'body' => $msg->body ?? '',
                    'sent_at' => $msg->delivered_at?->toIso8601String() ?? $msg->created_at?->toIso8601String(),
                ]),
            ]);

        $comments = Comment::query()
            ->whereHas('socialAccount', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with('socialAccount')
            ->orderByDesc('created_at_platform')
            ->limit(40)
            ->get()
            ->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'provider' => $comment->socialAccount->provider,
                'account_name' => $comment->socialAccount->name,
                'external_post_id' => $comment->external_id,
                'author_name' => $comment->author_name,
                'body' => $comment->body,
                'is_hidden' => $comment->is_hidden,
                'published_at' => $comment->created_at_platform?->toIso8601String(),
            ]);

        return Inertia::render('social/inbox', [
            'conversations' => $conversations,
            'comments' => $comments,
        ]);
    }

    public function reply(
        ReplyConversationRequest $request,
        Conversation $conversation,
        ReplyToConversation $replyAction,
    ): RedirectResponse {
        Gate::authorize('reply', $conversation);

        $user = $request->user();
        abort_if($user === null, 401);

        $replyAction->handle($conversation, $user, (string) $request->validated('body'));

        return redirect()->route('social.inbox.index')->with('status', 'Reply sent.');
    }

    public function toggleHideComment(Comment $comment, ProviderManager $providers): RedirectResponse
    {
        Gate::authorize('view', $comment->socialAccount->workspace);

        $newHidden = ! $comment->is_hidden;
        $provider = $providers->provider($comment->socialAccount->provider);

        if ($provider instanceof SupportsComments) {
            $provider->setCommentVisibility($comment, $newHidden);
        }

        $comment->update(['is_hidden' => $newHidden]);

        return redirect()->route('social.inbox.index')
            ->with('status', $newHidden ? 'Comment hidden.' : 'Comment unhidden.');
    }
}
