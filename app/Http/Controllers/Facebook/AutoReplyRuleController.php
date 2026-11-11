<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facebook\StoreAutoReplyRuleRequest;
use App\Http\Requests\Facebook\UpdateAutoReplyRuleRequest;
use App\Models\AutoReplyRule;
use App\Models\FacebookPage;
use App\Models\Message;
use App\Models\User;
use App\Services\Facebook\AutoReplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutoReplyRuleController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $activePage = $user->resolveCurrentPage();

        $rules = $activePage !== null
            ? AutoReplyRule::query()
                ->where('facebook_page_id', $activePage->id)
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
            : collect();

        $messages = $activePage !== null
            ? Message::query()
                ->with('autoReplyRule')
                ->where('facebook_page_id', $activePage->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(fn (Message $msg): array => [
                    'id' => $msg->id,
                    'channel' => $msg->channel,
                    'direction' => $msg->direction,
                    'external_id' => $msg->external_id,
                    'sender_id' => $msg->sender_id,
                    'sender_name' => $msg->sender_name,
                    'message_text' => $msg->message_text,
                    'reply_text' => $msg->reply_text,
                    'is_hidden' => $msg->is_hidden,
                    'status' => $msg->status,
                    'within_24h_window' => $msg->isWithin24HourWindow(),
                    'last_user_interaction_at' => $msg->last_user_interaction_at?->toIso8601String(),
                    'replied_at' => $msg->replied_at?->toIso8601String(),
                    'error_message' => $msg->error_message,
                    'rule_name' => $msg->autoReplyRule?->name,
                    'created_at' => $msg->created_at?->toIso8601String(),
                ])
            : collect();

        return Inertia::render('facebook/auto-replies', [
            'activePage' => $activePage ? [
                'id' => $activePage->id,
                'name' => $activePage->name,
                'page_id' => $activePage->page_id,
            ] : null,
            'rules' => $rules,
            'messages' => $messages,
        ]);
    }

    public function store(StoreAutoReplyRuleRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        AutoReplyRule::create([
            'user_id' => $user->id,
            ...$validated,
        ]);

        return back()->with('success', 'Auto-reply rule created.');
    }

    public function update(UpdateAutoReplyRuleRequest $request, AutoReplyRule $autoReplyRule): RedirectResponse
    {
        $autoReplyRule->update($request->validated());

        return back()->with('success', 'Auto-reply rule updated.');
    }

    public function toggle(AutoReplyRule $autoReplyRule): RedirectResponse
    {
        Gate::authorize('update', $autoReplyRule);

        $autoReplyRule->update(['is_active' => ! $autoReplyRule->is_active]);

        return back()->with('success', sprintf('Rule "%s" %s.', $autoReplyRule->name, $autoReplyRule->is_active ? 'enabled' : 'paused'));
    }

    public function destroy(AutoReplyRule $autoReplyRule): RedirectResponse
    {
        Gate::authorize('delete', $autoReplyRule);

        $autoReplyRule->delete();

        return back()->with('success', 'Auto-reply rule deleted.');
    }

    /**
     * Simulate an inbound Messenger message or Page comment to test rule matching, 24h window policy, and banned-word moderation.
     */
    public function simulate(Request $request, AutoReplyService $autoReplyService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'facebook_page_id' => ['required', 'integer', 'exists:facebook_pages,id'],
            'channel' => ['required', 'string', Rule::in(['messenger', 'comment'])],
            'sender_name' => ['required', 'string', 'max:80'],
            'message_text' => ['required', 'string', 'max:1000'],
            'hours_since_last_interaction' => ['nullable', 'integer', 'min:0', 'max:168'],
        ]);

        $page = FacebookPage::query()
            ->where('id', (int) $validated['facebook_page_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $hoursAgo = (int) ($validated['hours_since_last_interaction'] ?? 0);
        $interactedAt = now()->subHours($hoursAgo);
        $senderId = 'sim_'.random_int(100000, 999999);

        if ($validated['channel'] === 'messenger') {
            $message = $autoReplyService->handleInboundMessenger(
                page: $page,
                senderPsid: $senderId,
                messageText: (string) $validated['message_text'],
                externalId: 'm_sim_'.uniqid(),
                senderName: (string) $validated['sender_name'],
                interactedAt: $interactedAt,
            );
        } else {
            $message = $autoReplyService->handleInboundComment(
                page: $page,
                commentId: $page->page_id.'_sim_'.uniqid(),
                senderId: $senderId,
                commentText: (string) $validated['message_text'],
                postId: $page->page_id.'_900100',
                senderName: (string) $validated['sender_name'],
            );
        }

        return back()->with('success', sprintf('Simulated %s event processed with status: %s', $validated['channel'], $message->status));
    }
}
