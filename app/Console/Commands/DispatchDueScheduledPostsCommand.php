<?php

namespace App\Console\Commands;

use App\Services\Facebook\PostSchedulerService;
use Illuminate\Console\Command;

class DispatchDueScheduledPostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:dispatch-scheduled-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch queued jobs for all Facebook Page posts whose scheduled publish time has arrived.';

    public function handle(PostSchedulerService $schedulerService): int
    {
        $dispatched = $schedulerService->dispatchDuePosts();

        $this->info(sprintf('Dispatched %d due scheduled post(s).', $dispatched));

        return self::SUCCESS;
    }
}
