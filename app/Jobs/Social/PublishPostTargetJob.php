<?php

namespace App\Jobs\Social;

use App\Actions\Social\PublishPostTarget;
use App\Models\PostTarget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishPostTargetJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $postTargetId,
    ) {
        $this->onQueue('publishing');
    }

    public function handle(PublishPostTarget $publisher): void
    {
        $target = PostTarget::query()->find($this->postTargetId);
        if (! $target) {
            return;
        }

        $publisher->handle($target);
    }
}
