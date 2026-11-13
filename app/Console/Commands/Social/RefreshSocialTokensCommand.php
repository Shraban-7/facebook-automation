<?php

namespace App\Console\Commands\Social;

use App\Jobs\Social\RefreshAccountTokenJob;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class RefreshSocialTokensCommand extends Command
{
    protected $signature = 'social:refresh-tokens';

    protected $description = 'Queue token refresh jobs for social accounts expiring within 7 days.';

    public function handle(): int
    {
        $queued = 0;

        SocialAccount::query()
            ->where('status', 'active')
            ->whereHas('oauthToken', function ($query): void {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addDays(7));
            })
            ->chunkById(50, function ($accounts) use (&$queued): void {
                foreach ($accounts as $account) {
                    RefreshAccountTokenJob::dispatch($account->id);
                    $queued++;
                }
            });

        $this->info("Queued token refresh for {$queued} accounts.");

        return self::SUCCESS;
    }
}
