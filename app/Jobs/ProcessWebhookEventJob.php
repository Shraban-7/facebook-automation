<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Facebook\WebhookService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWebhookEventJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 120];

    public function __construct(
        public readonly int $webhookEventId,
    ) {}

    public function uniqueId(): string
    {
        return 'process_webhook_event_'.$this->webhookEventId;
    }

    public function handle(WebhookService $webhookService): void
    {
        $webhookEvent = WebhookEvent::find($this->webhookEventId);

        if ($webhookEvent === null) {
            return;
        }

        $webhookService->processStoredEvent($webhookEvent);
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEvent::where('id', $this->webhookEventId)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
        ]);
    }
}
