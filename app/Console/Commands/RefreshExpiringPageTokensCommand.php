<?php

namespace App\Console\Commands;

use App\Jobs\RefreshFacebookPageTokenJob;
use App\Models\FacebookPage;
use Illuminate\Console\Command;

class RefreshExpiringPageTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:refresh-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue token refresh jobs for Facebook Pages whose tokens expire within 7 days.';

    public function handle(): int
    {
        $pages = FacebookPage::query()
            ->whereIn('status', ['active', 'expired'])
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addDays(7))
            ->get();

        foreach ($pages as $page) {
            RefreshFacebookPageTokenJob::dispatch($page->id);
        }

        $this->info(sprintf('Queued token refresh for %d Facebook Page(s).', $pages->count()));

        return self::SUCCESS;
    }
}
