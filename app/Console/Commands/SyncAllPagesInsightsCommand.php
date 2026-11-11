<?php

namespace App\Console\Commands;

use App\Jobs\SyncPageInsightsJob;
use App\Models\FacebookPage;
use Illuminate\Console\Command;

class SyncAllPagesInsightsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:sync-insights {--days=30 : Number of days to pull}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue Page Insights synchronization jobs for all active Facebook Pages.';

    public function handle(): int
    {
        $days = max(7, min(90, (int) $this->option('days')));

        $pages = FacebookPage::query()->active()->get();

        foreach ($pages as $page) {
            SyncPageInsightsJob::dispatch($page->id, $days);
        }

        $this->info(sprintf('Queued insights sync (%d days) for %d active Facebook Page(s).', $days, $pages->count()));

        return self::SUCCESS;
    }
}
