<?php

namespace App\Jobs;

use App\Exceptions\FacebookGraphException;
use App\Models\FacebookPage;
use App\Services\Facebook\InsightsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPageInsightsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $facebookPageId,
        public readonly int $days = 30,
    ) {}

    public function uniqueId(): string
    {
        return sprintf('sync_page_insights_%d_%d', $this->facebookPageId, $this->days);
    }

    public function handle(InsightsService $insightsService): void
    {
        $page = FacebookPage::find($this->facebookPageId);

        if ($page === null || $page->status !== 'active') {
            return;
        }

        try {
            $insightsService->syncPageInsights($page, $this->days);
        } catch (FacebookGraphException $exception) {
            if (! $exception->isRetryable()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
