<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facebook\ConnectManualPageRequest;
use App\Jobs\ConnectFacebookPagesJob;
use App\Jobs\RefreshFacebookPageTokenJob;
use App\Jobs\SyncPageInsightsJob;
use App\Models\FacebookPage;
use App\Models\User;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\DemoWorkspaceSeederService;
use App\Services\Facebook\GraphApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FacebookPageController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $pages = $user->facebookPages()
            ->withCount(['posts', 'scheduledPosts', 'autoReplyRules', 'messages'])
            ->orderBy('name')
            ->get()
            ->map(fn (FacebookPage $page): array => [
                'id' => $page->id,
                'page_id' => $page->page_id,
                'name' => $page->name,
                'category' => $page->category,
                'avatar_url' => $page->avatar_url,
                'followers_count' => $page->followers_count,
                'permissions' => $page->permissions ?? config('facebook.scopes'),
                'tasks' => $page->tasks ?? [],
                'webhook_subscribed' => $page->webhook_subscribed,
                'status' => $page->status,
                'token_expires_at' => $page->token_expires_at?->toIso8601String(),
                'token_last_refreshed_at' => $page->token_last_refreshed_at?->toIso8601String(),
                'is_expired' => $page->isTokenExpired(),
                'is_expiring_soon' => $page->isTokenExpiringSoon(),
                'last_error_code' => $page->last_error_code,
                'last_error_message' => $page->last_error_message,
                'last_synced_at' => $page->last_synced_at?->toIso8601String(),
                'counts' => [
                    'posts' => $page->posts_count,
                    'scheduled_posts' => $page->scheduled_posts_count,
                    'auto_reply_rules' => $page->auto_reply_rules_count,
                    'messages' => $page->messages_count,
                ],
            ]);

        return Inertia::render('facebook/pages', [
            'pages' => $pages,
            'activePageId' => $user->active_facebook_page_id ?? $pages->first()['id'] ?? null,
            'requiredScopes' => config('facebook.scopes'),
            'oauthConfigured' => ! empty(config('facebook.app_id')) && ! empty(config('facebook.app_secret')),
            'webhookCallbackUrl' => rtrim((string) config('app.url'), '/').'/api/webhooks/facebook',
            'webhookVerifyToken' => (string) config('facebook.webhook_verify_token'),
            'graphVersion' => (string) config('facebook.graph_version', 'v21.0'),
        ]);
    }

    /**
     * Initiate the official Meta OAuth 2.0 dialog requesting minimal Page permissions.
     */
    public function redirectToFacebook(Request $request, GraphApiService $graphApi): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('facebook_oauth_state', $state);

        return redirect()->away($graphApi->getAuthorizationUrl($state));
    }

    /**
     * Handle Meta OAuth callback and queue the token exchange & Page synchronization job.
     */
    public function handleCallback(Request $request, ActivityLogService $activityLogger): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $expectedState = (string) $request->session()->pull('facebook_oauth_state', '');
        $providedState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $providedState)) {
            return redirect()->route('facebook.pages.index')
                ->with('error', 'Invalid OAuth state parameter. Please initiate Facebook Login again.');
        }

        if ($request->filled('error')) {
            $errorReason = (string) $request->query('error_description', 'Facebook Login authorization was cancelled.');

            $activityLogger->record([
                'user_id' => $user->id,
                'category' => 'oauth',
                'action' => 'oauth.callback.denied',
                'level' => 'warning',
                'message' => $errorReason,
            ]);

            return redirect()->route('facebook.pages.index')->with('error', $errorReason);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()->route('facebook.pages.index')
                ->with('error', 'Missing authorization code from Meta OAuth callback.');
        }

        ConnectFacebookPagesJob::dispatch(
            userId: $user->id,
            authorizationCode: $code,
            redirectUri: (string) config('facebook.redirect_uri'),
        );

        return redirect()->route('facebook.pages.index')
            ->with('success', 'Facebook authorization queued. Your managed Pages and long-lived encrypted tokens are syncing in the background.');
    }

    /**
     * Connect a Page directly using a long-lived Page Access Token (encrypted at rest).
     */
    public function storeManual(ConnectManualPageRequest $request, ActivityLogService $activityLogger): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $page = FacebookPage::updateOrCreate(
            [
                'user_id' => $user->id,
                'page_id' => (string) $validated['page_id'],
            ],
            [
                'name' => (string) $validated['name'],
                'category' => $validated['category'] ?? 'Brand & Business',
                'followers_count' => (int) ($validated['followers_count'] ?? 0),
                'access_token' => (string) $validated['access_token'],
                'token_expires_at' => now()->addDays(60),
                'token_last_refreshed_at' => now(),
                'permissions' => config('facebook.scopes'),
                'tasks' => ['MANAGE', 'CREATE_CONTENT', 'MODERATE', 'MESSAGING'],
                'webhook_subscribed' => true,
                'status' => 'active',
                'last_error_code' => null,
                'last_error_message' => null,
                'last_synced_at' => now(),
            ]
        );

        if ($user->active_facebook_page_id === null) {
            $user->update(['active_facebook_page_id' => $page->id]);
        }

        SyncPageInsightsJob::dispatch($page->id, 30);

        $activityLogger->record([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'category' => 'oauth',
            'action' => 'oauth.page.connected_manual',
            'level' => 'info',
            'message' => sprintf('Connected Facebook Page "%s" (ID: %s) with encrypted long-lived token.', $page->name, $page->page_id),
        ]);

        return back()->with('success', sprintf('Connected "%s" and queued initial insights sync.', $page->name));
    }

    /**
     * Switch the user's active Facebook Page across the workspace.
     */
    public function switchActive(Request $request, FacebookPage $facebookPage): RedirectResponse
    {
        Gate::authorize('update', $facebookPage);

        /** @var User $user */
        $user = $request->user();
        $user->update(['active_facebook_page_id' => $facebookPage->id]);

        return back()->with('success', sprintf('Switched active workspace to %s.', $facebookPage->name));
    }

    /**
     * Queue a background token refresh job for a specific Facebook Page.
     */
    public function refreshToken(FacebookPage $facebookPage): RedirectResponse
    {
        Gate::authorize('update', $facebookPage);

        RefreshFacebookPageTokenJob::dispatch($facebookPage->id);

        return back()->with('success', sprintf('Queued token refresh job for %s.', $facebookPage->name));
    }

    /**
     * Disconnect and remove a Facebook Page from the workspace.
     */
    public function destroy(Request $request, FacebookPage $facebookPage): RedirectResponse
    {
        Gate::authorize('delete', $facebookPage);

        /** @var User $user */
        $user = $request->user();
        $name = $facebookPage->name;

        if ($user->active_facebook_page_id === $facebookPage->id) {
            $nextPage = $user->facebookPages()->where('id', '!=', $facebookPage->id)->first();
            $user->update(['active_facebook_page_id' => $nextPage?->id]);
        }

        $facebookPage->delete();

        return back()->with('success', sprintf('Disconnected %s.', $name));
    }

    /**
     * Seed realistic multi-page demo data for local evaluation.
     */
    public function seedDemo(Request $request, DemoWorkspaceSeederService $seeder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $page = $seeder->seedForUser($user);

        return back()->with('success', sprintf('Loaded sample Pages, scheduled posts, auto-reply rules, and 90-day insights for %s.', $page->name));
    }
}
