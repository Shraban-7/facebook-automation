<?php

namespace App\Jobs;

use App\Exceptions\FacebookGraphException;
use App\Models\Message;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\GraphApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ModerateCommentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public readonly int $messageId,
        public readonly bool $hide = true,
    ) {}

    public function handle(GraphApiService $graphApi, ActivityLogService $activityLogger): void
    {
        $message = Message::with('facebookPage')->find($this->messageId);

        if ($message === null || $message->facebookPage === null || empty($message->external_id)) {
            return;
        }

        $page = $message->facebookPage;

        try {
            $graphApi->moderateComment($page, (string) $message->external_id, $this->hide);

            $message->update([
                'is_hidden' => $this->hide,
                'status' => 'moderated',
                'replied_at' => now(),
            ]);

            $activityLogger->record([
                'user_id' => $page->user_id,
                'facebook_page_id' => $page->id,
                'category' => 'moderation',
                'action' => 'comment.hidden',
                'level' => 'warning',
                'message' => sprintf('Comment %s hidden automatically due to banned-word moderation rule.', $message->external_id),
                'context' => [
                    'message_id' => $message->id,
                    'comment_id' => $message->external_id,
                ],
            ]);
        } catch (FacebookGraphException $exception) {
            $message->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            if (! $exception->isRetryable()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
