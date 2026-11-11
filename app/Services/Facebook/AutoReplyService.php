<?php

namespace App\Services\Facebook;

use App\Jobs\ModerateCommentJob;
use App\Jobs\SendAutoReplyJob;
use App\Models\AutoReplyRule;
use App\Models\FacebookPage;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AutoReplyService
{
    public function __construct(
        private readonly ActivityLogService $activityLogger,
    ) {}

    /**
     * Evaluate an inbound Messenger message against the Page's active rules and 24-hour policy window.
     */
    public function handleInboundMessenger(
        FacebookPage $page,
        string $senderPsid,
        string $messageText,
        ?string $externalId = null,
        ?string $senderName = null,
        ?CarbonInterface $interactedAt = null,
    ): Message {
        $interactionTime = $interactedAt ? Carbon::instance($interactedAt) : now();

        $message = Message::create([
            'facebook_page_id' => $page->id,
            'channel' => 'messenger',
            'direction' => 'inbound',
            'external_id' => $externalId,
            'sender_id' => $senderPsid,
            'sender_name' => $senderName ?? 'Messenger User #'.Str::substr($senderPsid, -4),
            'recipient_id' => $page->page_id,
            'message_text' => $messageText,
            'status' => 'received',
            'last_user_interaction_at' => $interactionTime,
        ]);

        $matchedRule = $this->findMatchingRule($page, 'messenger', $messageText);

        if ($matchedRule === null) {
            return $message;
        }

        if ($matchedRule->respect_24h_window && ! $message->isWithin24HourWindow()) {
            $message->update([
                'auto_reply_rule_id' => $matchedRule->id,
                'status' => 'skipped_outside_window',
                'error_message' => 'Skipped: Last user interaction exceeds Meta\'s 24-hour standard messaging window.',
            ]);

            $this->activityLogger->record([
                'user_id' => $page->user_id,
                'facebook_page_id' => $page->id,
                'category' => 'auto_reply',
                'action' => 'messenger.window_blocked',
                'level' => 'warning',
                'message' => sprintf('Blocked auto-reply to PSID %s: Outside 24-hour messaging policy window.', $senderPsid),
                'context' => [
                    'message_id' => $message->id,
                    'rule_id' => $matchedRule->id,
                    'last_user_interaction_at' => $message->last_user_interaction_at?->toIso8601String(),
                ],
            ]);

            return $message;
        }

        if ($this->isInSenderCooldown($page->id, $matchedRule->id, $senderPsid, $matchedRule->cooldown_seconds)) {
            $message->update([
                'auto_reply_rule_id' => $matchedRule->id,
                'status' => 'skipped_policy',
                'error_message' => 'Skipped: Sender is within anti-spam cooldown window.',
            ]);

            return $message;
        }

        $renderedReply = $this->renderTemplate($matchedRule->reply_template, $page, $message->sender_name);

        $message->update([
            'auto_reply_rule_id' => $matchedRule->id,
            'reply_text' => $renderedReply,
            'status' => 'queued',
        ]);

        $matchedRule->increment('match_count');
        $matchedRule->update(['last_triggered_at' => now()]);

        SendAutoReplyJob::dispatch($message->id);

        return $message;
    }

    /**
     * Evaluate an inbound Page feed comment for banned-word moderation and keyword auto-reply.
     */
    public function handleInboundComment(
        FacebookPage $page,
        string $commentId,
        string $senderId,
        string $commentText,
        ?string $postId = null,
        ?string $senderName = null,
    ): Message {
        // Ignore comments authored by the Page itself to prevent infinite reply loops
        if ($senderId === $page->page_id) {
            return Message::create([
                'facebook_page_id' => $page->id,
                'channel' => 'comment',
                'direction' => 'outbound',
                'external_id' => $commentId,
                'parent_external_id' => $postId,
                'sender_id' => $senderId,
                'sender_name' => $page->name,
                'message_text' => $commentText,
                'status' => 'replied',
            ]);
        }

        $message = Message::create([
            'facebook_page_id' => $page->id,
            'channel' => 'comment',
            'direction' => 'inbound',
            'external_id' => $commentId,
            'parent_external_id' => $postId,
            'sender_id' => $senderId,
            'sender_name' => $senderName ?? 'Facebook User',
            'recipient_id' => $page->page_id,
            'message_text' => $commentText,
            'status' => 'received',
            'last_user_interaction_at' => now(),
        ]);

        // 1. Check banned words across active comment/both rules first
        $moderationRule = $this->findBannedWordRule($page, $commentText);

        if ($moderationRule !== null && $moderationRule->moderation_action === 'hide') {
            $message->update([
                'auto_reply_rule_id' => $moderationRule->id,
                'is_hidden' => true,
                'status' => 'moderated',
            ]);

            $moderationRule->increment('match_count');
            $moderationRule->update(['last_triggered_at' => now()]);

            ModerateCommentJob::dispatch($message->id, true);

            return $message;
        }

        // 2. Match keyword or fallback auto-reply rule
        $matchedRule = $this->findMatchingRule($page, 'comment', $commentText);

        if ($matchedRule === null) {
            return $message;
        }

        $renderedReply = $this->renderTemplate($matchedRule->reply_template, $page, $message->sender_name);

        $message->update([
            'auto_reply_rule_id' => $matchedRule->id,
            'reply_text' => $renderedReply,
            'status' => 'queued',
        ]);

        $matchedRule->increment('match_count');
        $matchedRule->update(['last_triggered_at' => now()]);

        SendAutoReplyJob::dispatch($message->id);

        return $message;
    }

    /**
     * Find the highest-priority matching rule for the given channel and text, falling back to 'fallback' trigger type.
     */
    public function findMatchingRule(FacebookPage $page, string $channel, string $text): ?AutoReplyRule
    {
        $rules = AutoReplyRule::query()
            ->where('facebook_page_id', $page->id)
            ->where('is_active', true)
            ->whereIn('channel', [$channel, 'both'])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $normalizedText = Str::lower(trim($text));
        $fallbackRule = null;

        foreach ($rules as $rule) {
            if ($rule->trigger_type === 'fallback') {
                $fallbackRule ??= $rule;

                continue;
            }

            if ($this->ruleMatchesText($rule, $normalizedText, $text)) {
                return $rule;
            }
        }

        return $fallbackRule;
    }

    /**
     * Find any active rule whose `banned_words` list matches the comment text.
     */
    public function findBannedWordRule(FacebookPage $page, string $commentText): ?AutoReplyRule
    {
        $rules = AutoReplyRule::query()
            ->where('facebook_page_id', $page->id)
            ->where('is_active', true)
            ->whereIn('channel', ['comment', 'both'])
            ->where('moderation_action', 'hide')
            ->orderBy('priority')
            ->get();

        $normalizedText = Str::lower($commentText);

        foreach ($rules as $rule) {
            $bannedWords = $rule->banned_words ?? [];
            foreach ($bannedWords as $word) {
                $cleanWord = Str::lower(trim((string) $word));
                if ($cleanWord !== '' && Str::contains($normalizedText, $cleanWord)) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * Replace dynamic placeholders in a canned reply template.
     */
    public function renderTemplate(string $template, FacebookPage $page, ?string $senderName = null): string
    {
        return str_replace(
            ['{{sender_name}}', '{{page_name}}'],
            [$senderName ?: 'there', $page->name],
            $template
        );
    }

    private function ruleMatchesText(AutoReplyRule $rule, string $normalizedText, string $rawText): bool
    {
        $keywords = $rule->keywords ?? [];

        if ($keywords === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            $cleanKeyword = Str::lower(trim((string) $keyword));

            if ($cleanKeyword === '') {
                continue;
            }

            $matched = match ($rule->trigger_type) {
                'exact' => $normalizedText === $cleanKeyword,
                'regex' => @preg_match('/'.$keyword.'/iu', $rawText) === 1,
                default => Str::contains($normalizedText, $cleanKeyword),
            };

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private function isInSenderCooldown(int $pageId, int $ruleId, string $senderId, int $cooldownSeconds): bool
    {
        if ($cooldownSeconds <= 0) {
            return false;
        }

        $cacheKey = sprintf('fb_autoreply_cooldown:page:%d:rule:%d:sender:%s', $pageId, $ruleId, $senderId);

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, $cooldownSeconds);

        return false;
    }
}
