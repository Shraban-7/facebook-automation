<?php

namespace App\Jobs;

use App\Exceptions\FacebookGraphException;
use App\Models\FacebookPage;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\GraphApiService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshFacebookPageTokenJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly int $facebookPageId,
    ) {}

    public function uniqueId(): string
    {
        return 'refresh_page_token_'.$this->facebookPageId;
    }

    public function handle(GraphApiService $graphApi, ActivityLogService $activityLogger): void
    {
        $page = FacebookPage::with('user')->find($this->facebookPageId);

        if ($page === null || $page->user === null) {
            return;
        }

        $sourceUserToken = $page->user_access_token ?? $page->user->facebook_user_token;

        if ($sourceUserToken === null || $sourceUserToken === '') {
            $page->update([
                'status' => 'expired',
                'last_error_code' => 190,
                'last_error_message' => 'Missing user access token to refresh Page token. Please reconnect via Facebook Login.',
            ]);

            return;
        }

        try {
            $longToken = $graphApi->exchangeForLongLivedUserToken($page->user, $sourceUserToken);
            $expiresAt = now()->addSeconds($longToken['expires_in']);

            $pages = $graphApi->fetchManagedPages($page->user, $longToken['access_token']);

            foreach ($pages as $rawPage) {
                if ((string) ($rawPage['id'] ?? '') === $page->page_id && ! empty($rawPage['access_token'])) {
                    $page->update([
                        'access_token' => (string) $rawPage['access_token'],
                        'user_access_token' => $longToken['access_token'],
                        'token_expires_at' => $expiresAt,
                        'token_last_refreshed_at' => now(),
                        'status' => 'active',
                        'last_error_code' => null,
                        'last_error_message' => null,
                    ]);

                    $activityLogger->record([
                        'user_id' => $page->user_id,
                        'facebook_page_id' => $page->id,
                        'category' => 'oauth',
                        'action' => 'oauth.token.refreshed',
                        'level' => 'info',
                        'message' => sprintf('Refreshed long-lived Page access token for "%s".', $page->name),
                    ]);

                    return;
                }
            }
        } catch (FacebookGraphException $exception) {
            if (! $exception->isRetryable()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
