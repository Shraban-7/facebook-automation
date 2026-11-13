<?php

namespace App\Console\Commands\Social;

use App\Jobs\Social\PullAccountMetricsJob;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class PullSocialMetricsCommand extends Command
{
    protected $signature = 'social:pull-metrics {--days=7 : Number of days to fetch}';

    protected $description = 'Queue normalized analytics metric pulls for active social accounts.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $queued = 0;

        SocialAccount::query()
            ->where('status', 'active')
            ->chunkById(50, function ($accounts) use ($days, &$queued): void {
                foreach ($accounts as $account) {
                    PullAccountMetricsJob::dispatch($account->id, $days);
                    $queued++;
                }
            });

        $this->info("Queued metric pulls for {$queued} active accounts.");

        return self::SUCCESS;
    }
}
