<?php

namespace App\Console\Commands\Social;

use App\Enums\PostStatus;
use App\Jobs\Social\PublishPostTargetJob;
use App\Models\PostTarget;
use Illuminate\Console\Command;

class SweepDueTargetsCommand extends Command
{
    protected $signature = 'social:sweep-due-targets';

    protected $description = 'Dispatch due scheduled post targets onto the publishing queue.';

    public function handle(): int
    {
        $dispatched = 0;

        PostTarget::query()
            ->where('status', PostStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->chunkById(100, function ($targets) use (&$dispatched): void {
                foreach ($targets as $target) {
                    $target->update(['status' => PostStatus::Queued->value]);
                    PublishPostTargetJob::dispatch($target->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} due post targets.");

        return self::SUCCESS;
    }
}
