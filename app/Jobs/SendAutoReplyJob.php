<?php

namespace App\Jobs;

use App\Exceptions\FacebookGraphException;
use App\Models\Message;
use App\Services\Facebook\GraphApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAutoReplyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(GraphApiService $graphApi): void
    {
        $message = Message::with('facebookPage')->find($this->messageId);

        if ($message === null || $message->facebookPage === null || $message->status === 'replied') {
            return;
        }

        $page = $message->facebookPage;
        $replyText = (string) $message->reply_text;

        if ($replyText === '') {
            return;
        }

        if ($message->channel === 'messenger' && ! $message->isWithin24HourWindow()) {
            $message->update([
                'status' => 'skipped_outside_window',
                'error_message' => '24-hour standard messaging window expired before queued job executed.',
            ]);

            return;
        }

        try {
            if ($message->channel === 'messenger') {
                $graphApi->sendMessengerReply($page, $message->sender_id, $replyText);
            } else {
                $commentId = (string) ($message->external_id ?? '');
                if ($commentId !== '') {
                    $graphApi->replyToComment($page, $commentId, $replyText);
                }
            }

            $message->update([
                'status' => 'replied',
                'replied_at' => now(),
                'error_message' => null,
            ]);
        } catch (FacebookGraphException $exception) {
            if ($exception->isOutsideMessagingWindow()) {
                $message->update([
                    'status' => 'skipped_outside_window',
                    'error_message' => $exception->getMessage(),
                ]);

                return;
            }

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
