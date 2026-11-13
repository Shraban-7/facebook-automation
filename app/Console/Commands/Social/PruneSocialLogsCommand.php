<?php

namespace App\Console\Commands\Social;

use App\Models\ApiCallLog;
use App\Models\PublishAttempt;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class PruneSocialLogsCommand extends Command
{
    protected $signature = 'social:prune';

    protected $description = 'Prune aged webhook events, API call logs, and publish attempts per retention policy.';

    public function handle(): int
    {
        $webhookDays = (int) config('social.retention.webhook_events_days', 30);
        $apiDays = (int) config('social.retention.api_call_logs_days', 30);
        $attemptDays = (int) config('social.retention.publish_attempts_days', 90);

        $webhooksDeleted = WebhookEvent::query()
            ->where('created_at', '<', now()->subDays($webhookDays))
            ->delete();

        $apiDeleted = ApiCallLog::query()
            ->where('created_at', '<', now()->subDays($apiDays))
            ->delete();

        $attemptsDeleted = PublishAttempt::query()
            ->where('created_at', '<', now()->subDays($attemptDays))
            ->delete();

        $this->info("Pruned: webhooks={$webhooksDeleted}, api_logs={$apiDeleted}, attempts={$attemptsDeleted}");

        return self::SUCCESS;
    }
}
