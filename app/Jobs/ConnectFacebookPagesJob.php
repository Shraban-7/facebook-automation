<?php

namespace App\Jobs;

use App\Exceptions\FacebookGraphException;
use App\Models\FacebookPage;
use App\Models\User;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\GraphApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ConnectFacebookPagesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $userId,
        public readonly string $authorizationCode,
        public readonly ?string $redirectUri = null,
    ) {}

    public function handle(GraphApiService $graphApi, ActivityLogService $activityLogger): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $shortTokenData = $graphApi->exchangeCodeForUserToken(
            user: $user,
            code: $this->authorizationCode,
            redirectUri: $this->redirectUri,
        );

        $longTokenData = $graphApi->exchangeForLongLivedUserToken(
            user: $user,
            shortLivedToken: $shortTokenData['access_token'],
        );

        $userTokenExpiresAt = now()->addSeconds($longTokenData['expires_in']);

        $user->update([
            'facebook_user_token' => $longTokenData['access_token'],
            'facebook_token_expires_at' => $userTokenExpiresAt,
        ]);

        $pagesData = $graphApi->fetchManagedPages($user, $longTokenData['access_token']);

        $firstConnectedPage = null;

        foreach ($pagesData as $rawPage) {
            $pageExternalId = (string) ($rawPage['id'] ?? '');
            $pageAccessToken = (string) ($rawPage['access_token'] ?? '');

            if ($pageExternalId === '' || $pageAccessToken === '') {
                continue;
            }

            $avatarUrl = is_array($rawPage['picture']['data'] ?? null)
                ? (string) ($rawPage['picture']['data']['url'] ?? '')
                : null;

            /** @var array<int, string> $tasks */
            $tasks = is_array($rawPage['tasks'] ?? null) ? array_values($rawPage['tasks']) : [];

            $page = FacebookPage::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'page_id' => $pageExternalId,
                ],
                [
                    'name' => (string) ($rawPage['name'] ?? 'Facebook Page'),
                    'category' => isset($rawPage['category']) ? (string) $rawPage['category'] : null,
                    'avatar_url' => $avatarUrl ?: null,
                    'followers_count' => isset($rawPage['followers_count']) ? (int) $rawPage['followers_count'] : 0,
                    'access_token' => $pageAccessToken,
                    'user_access_token' => $longTokenData['access_token'],
                    'token_expires_at' => $userTokenExpiresAt,
                    'token_last_refreshed_at' => now(),
                    'permissions' => config('facebook.scopes'),
                    'tasks' => $tasks,
                    'status' => 'active',
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'last_synced_at' => now(),
                ]
            );

            try {
                $subscribed = $graphApi->subscribePageWebhook($page);
                $page->update(['webhook_subscribed' => $subscribed]);
            } catch (FacebookGraphException) {
                // Webhook subscription failure is logged by GraphApiService; keep page connected.
            }

            $firstConnectedPage ??= $page;

            SyncPageInsightsJob::dispatch($page->id, 30);
        }

        if ($user->active_facebook_page_id === null && $firstConnectedPage !== null) {
            $user->update(['active_facebook_page_id' => $firstConnectedPage->id]);
        }

        $activityLogger->record([
            'user_id' => $user->id,
            'facebook_page_id' => $firstConnectedPage?->id,
            'category' => 'oauth',
            'action' => 'oauth.pages.connected',
            'level' => 'info',
            'message' => sprintf('Connected and synchronized %d Facebook Page(s) via Meta OAuth.', count($pagesData)),
        ]);
    }
}
