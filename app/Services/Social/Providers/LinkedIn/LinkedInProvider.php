<?php

namespace App\Services\Social\Providers\LinkedIn;

use App\Contracts\Social\SocialProvider;
use App\Contracts\Social\SupportsAnalytics;
use App\DTO\Social\AuthState;
use App\DTO\Social\ConnectionResult;
use App\DTO\Social\DateRange;
use App\DTO\Social\DiscoveredAccount;
use App\DTO\Social\NormalizedMetric;
use App\DTO\Social\ProviderCapabilities;
use App\DTO\Social\PublishResult;
use App\DTO\Social\TokenSet;
use App\DTO\Social\ValidationResult;
use App\Enums\AccountKind;
use App\Enums\ErrorClass;
use App\Exceptions\SocialProviderException;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Services\Social\TokenVault;
use Illuminate\Support\Facades\Http;

class LinkedInProvider implements SocialProvider, SupportsAnalytics
{
    public function __construct(
        private readonly TokenVault $tokenVault,
    ) {}

    public function key(): string
    {
        return 'linkedin';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: true,
            images: true,
            video: true,
            carousel: false,
            link: true,
            maxTextLength: 3000,
            maxImages: 9,
            maxVideoSeconds: 600,
            supportsNativeScheduling: false,
            supportsMessaging: false,
            supportsComments: false,
            supportsAnalytics: true,
            supportsWebhooks: false,
            requiresPublicMediaUrl: false,
        );
    }

    public function authorizationUrl(AuthState $state): string
    {
        $oauthBase = rtrim((string) config('social.providers.linkedin.oauth_url', 'https://www.linkedin.com/oauth/v2'), '/');
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.linkedin.scopes', []);

        return sprintf(
            '%s/authorization?%s',
            $oauthBase,
            http_build_query([
                'response_type' => 'code',
                'client_id' => (string) config('social.providers.linkedin.client_id'),
                'redirect_uri' => $state->redirectUri,
                'state' => $state->state,
                'scope' => implode(' ', $scopes),
            ])
        );
    }

    public function handleCallback(array $query, AuthState $state): ConnectionResult
    {
        $code = (string) ($query['code'] ?? '');
        if ($code === '') {
            throw new SocialProviderException('Missing LinkedIn OAuth authorization code.', ErrorClass::Fatal, 400);
        }

        $oauthBase = rtrim((string) config('social.providers.linkedin.oauth_url', 'https://www.linkedin.com/oauth/v2'), '/');
        $apiBase = rtrim((string) config('social.providers.linkedin.base_url', 'https://api.linkedin.com'), '/');

        $tokenRes = Http::asForm()->timeout(15)->post("{$oauthBase}/accessToken", [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $state->redirectUri,
            'client_id' => (string) config('social.providers.linkedin.client_id'),
            'client_secret' => (string) config('social.providers.linkedin.client_secret'),
        ]);

        if ($tokenRes->failed()) {
            throw $this->mapHttpFailure($tokenRes->status(), $tokenRes->json(), 'LinkedIn OAuth token exchange failed.');
        }

        $accessToken = (string) $tokenRes->json('access_token');
        $refreshToken = $tokenRes->json('refresh_token');
        $expiresIn = (int) $tokenRes->json('expires_in', 5184000);
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.linkedin.scopes', []);

        $tokenSet = new TokenSet(
            accessToken: $accessToken,
            refreshToken: is_string($refreshToken) ? $refreshToken : null,
            expiresAt: now()->addSeconds($expiresIn),
            scopes: $scopes,
        );

        $accounts = [];

        $userRes = Http::withToken($accessToken)->timeout(15)->get("{$apiBase}/v2/userinfo");
        if ($userRes->successful()) {
            $sub = (string) $userRes->json('sub', '');
            if ($sub !== '') {
                $accounts[] = new DiscoveredAccount(
                    externalId: "urn:li:person:{$sub}",
                    name: (string) $userRes->json('name', 'LinkedIn Member'),
                    kind: AccountKind::Profile,
                    tokenSet: $tokenSet,
                    avatarUrl: $userRes->json('picture'),
                    settings: ['urn' => "urn:li:person:{$sub}"],
                );
            }
        }

        $orgRes = Http::withHeaders($this->restHeaders($accessToken))
            ->timeout(15)
            ->get("{$apiBase}/rest/organizationAcls", [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'state' => 'APPROVED',
            ]);

        if ($orgRes->successful()) {
            foreach ((array) $orgRes->json('elements', []) as $element) {
                $orgUrn = (string) ($element['organization'] ?? '');
                if ($orgUrn !== '') {
                    $accounts[] = new DiscoveredAccount(
                        externalId: $orgUrn,
                        name: (string) ($element['organizationName'] ?? $orgUrn),
                        kind: AccountKind::Organization,
                        tokenSet: $tokenSet,
                        settings: ['urn' => $orgUrn],
                    );
                }
            }
        }

        if ($accounts === []) {
            throw new SocialProviderException('No LinkedIn profile or organization accounts discovered.', ErrorClass::Fatal, 422);
        }

        return new ConnectionResult(accounts: $accounts);
    }

    public function refreshToken(SocialAccount $account): TokenSet
    {
        $refreshToken = $this->tokenVault->getRefreshToken($account);
        if (! $refreshToken) {
            throw new SocialProviderException('No LinkedIn refresh token stored.', ErrorClass::AuthExpired, 401);
        }

        $oauthBase = rtrim((string) config('social.providers.linkedin.oauth_url', 'https://www.linkedin.com/oauth/v2'), '/');
        $res = Http::asForm()->timeout(15)->post("{$oauthBase}/accessToken", [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => (string) config('social.providers.linkedin.client_id'),
            'client_secret' => (string) config('social.providers.linkedin.client_secret'),
        ]);

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'Failed to refresh LinkedIn token.');
        }

        return new TokenSet(
            accessToken: (string) $res->json('access_token'),
            refreshToken: (string) $res->json('refresh_token', $refreshToken),
            expiresAt: now()->addSeconds((int) $res->json('expires_in', 5184000)),
        );
    }

    public function revoke(SocialAccount $account): void
    {
        $this->tokenVault->purge($account);
    }

    public function validate(PostTarget $target): ValidationResult
    {
        $content = $target->content;
        $body = (string) ($target->body_override ?? $content->body ?? '');
        $errors = [];

        if (mb_strlen($body) > 3000) {
            $errors[] = 'LinkedIn post commentary cannot exceed 3000 characters.';
        }

        if ($body === '' && $content->mediaAssets->isEmpty() && ! $content->link_url) {
            $errors[] = 'LinkedIn post requires text, media, or a link.';
        }

        return $errors === [] ? ValidationResult::ok() : ValidationResult::fail($errors);
    }

    public function publish(PostTarget $target, string $idempotencyKey): PublishResult
    {
        $validation = $this->validate($target);
        if (! $validation->valid) {
            throw new SocialProviderException(implode(' ', $validation->errors), ErrorClass::Fatal, 422);
        }

        $account = $target->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);
        if (! $token) {
            throw new SocialProviderException('Missing LinkedIn access token.', ErrorClass::AuthExpired, 401);
        }

        $apiBase = rtrim((string) config('social.providers.linkedin.base_url', 'https://api.linkedin.com'), '/');
        $content = $target->content;
        $body = $this->escapeLittleText((string) ($target->body_override ?? $content->body ?? ''));
        $authorUrn = $account->external_id;

        $payload = [
            'author' => $authorUrn,
            'commentary' => $body,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $firstMedia = $content->mediaAssets->first();
        if ($firstMedia) {
            $isVideo = str_starts_with($firstMedia->mime, 'video/');
            $uploadEndpoint = $isVideo ? '/rest/videos?action=initializeUpload' : '/rest/images?action=initializeUpload';
            $initPayload = [
                'initializeUploadRequest' => [
                    'owner' => $authorUrn,
                ],
            ];
            if ($isVideo) {
                $initPayload['initializeUploadRequest']['fileSizeBytes'] = (int) $firstMedia->size_bytes;
            }

            $initRes = Http::withHeaders($this->restHeaders($token))
                ->timeout(20)
                ->post("{$apiBase}{$uploadEndpoint}", $initPayload);

            if ($initRes->failed()) {
                throw $this->mapHttpFailure($initRes->status(), $initRes->json(), 'LinkedIn media upload initialization failed.');
            }

            $assetUrn = (string) ($initRes->json('value.image') ?? $initRes->json('value.video') ?? '');
            if ($assetUrn !== '') {
                $payload['content'] = [
                    'media' => [
                        'id' => $assetUrn,
                    ],
                ];
            }
        } elseif ($content->link_url) {
            $payload['content'] = [
                'article' => [
                    'source' => $content->link_url,
                    'title' => $content->title ?? $content->link_url,
                ],
            ];
        }

        $res = Http::withHeaders($this->restHeaders($token))
            ->timeout(20)
            ->post("{$apiBase}/rest/posts", $payload);

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'LinkedIn post publish failed.');
        }

        $postUrn = (string) ($res->header('x-restli-id') ?: $res->json('id', 'urn:li:share:'.md5($idempotencyKey)));

        return new PublishResult(
            externalId: $postUrn,
            externalUrl: "https://www.linkedin.com/feed/update/{$postUrn}",
            rawResponse: $res->json() ?? ['id' => $postUrn],
        );
    }

    public function delete(PostTarget $target): void
    {
        if (! $target->external_id) {
            return;
        }
        $token = $this->tokenVault->getAccessToken($target->socialAccount);
        if (! $token) {
            return;
        }

        $apiBase = rtrim((string) config('social.providers.linkedin.base_url', 'https://api.linkedin.com'), '/');
        $encodedUrn = rawurlencode($target->external_id);

        Http::withHeaders($this->restHeaders($token))
            ->timeout(15)
            ->delete("{$apiBase}/rest/posts/{$encodedUrn}");
    }

    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable
    {
        $token = $this->tokenVault->getAccessToken($account);
        if (! $token || ! str_contains($account->external_id, 'organization')) {
            return [];
        }

        $apiBase = rtrim((string) config('social.providers.linkedin.base_url', 'https://api.linkedin.com'), '/');
        $res = Http::withHeaders($this->restHeaders($token))
            ->timeout(15)
            ->get("{$apiBase}/rest/organizationalEntityShareStatistics", [
                'q' => 'organizationalEntity',
                'organizationalEntity' => $account->external_id,
            ]);

        if ($res->failed()) {
            return [];
        }

        $stats = $res->json('elements.0.totalShareStatistics', []);

        return [
            new NormalizedMetric('impressions', $range->until, (float) ($stats['impressionCount'] ?? 0)),
            new NormalizedMetric('clicks', $range->until, (float) ($stats['clickCount'] ?? 0)),
            new NormalizedMetric('likes', $range->until, (float) ($stats['likeCount'] ?? 0)),
            new NormalizedMetric('comments', $range->until, (float) ($stats['commentCount'] ?? 0)),
            new NormalizedMetric('shares', $range->until, (float) ($stats['shareCount'] ?? 0)),
        ];
    }

    /**
     * @return array<int, NormalizedMetric>
     */
    public function fetchPostMetrics(PostTarget $target): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    private function restHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Linkedin-Version' => (string) config('social.providers.linkedin.api_version', '202501'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }

    /**
     * Escape LinkedIn Little Text Format reserved characters.
     */
    private function escapeLittleText(string $text): string
    {
        return str_replace(
            ['\\', '|', '{', '}', '@', '[', ']', '(', ')', '<', '>', '#', '*', '_', '~'],
            ['\\\\', '\\|', '\\{', '\\}', '\\@', '\\[', '\\]', '\\(', '\\)', '\\<', '\\>', '\\#', '\\*', '\\_', '\\~'],
            $text
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mapHttpFailure(int $status, ?array $payload, string $defaultMessage): SocialProviderException
    {
        $message = (string) ($payload['message'] ?? $payload['error_description'] ?? $defaultMessage);

        $class = match (true) {
            $status === 401 => ErrorClass::AuthExpired,
            $status === 429 => ErrorClass::RateLimited,
            $status >= 500 => ErrorClass::Retryable,
            default => ErrorClass::Fatal,
        };

        return new SocialProviderException($message, $class, $status);
    }
}
