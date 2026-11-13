<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\ConnectAccount;
use App\Actions\Social\DisconnectAccount;
use App\DTO\Social\AuthState;
use App\DTO\Social\DiscoveredAccount;
use App\DTO\Social\TokenSet;
use App\Enums\AccountKind;
use App\Enums\Provider;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\ProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SocialAccountController extends Controller
{
    public function index(Request $request, ProviderManager $providers): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();

        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('provider')
            ->orderBy('name')
            ->get()
            ->map(function (SocialAccount $account): array {
                $enum = Provider::tryFrom($account->provider);

                return [
                    'id' => $account->id,
                    'provider' => $account->provider,
                    'provider_label' => $enum?->label() ?? ucfirst($account->provider),
                    'kind' => $account->kind,
                    'external_id' => $account->external_id,
                    'display_name' => $account->name,
                    'handle' => $account->username,
                    'avatar_url' => $account->avatar_url,
                    'status' => $account->status,
                    'reauth_required' => $account->status === 'reauth_required',
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                ];
            });

        $providerCatalog = collect(['facebook', 'instagram', 'threads', 'linkedin', 'youtube', 'fake'])
            ->map(function (string $key) use ($providers): array {
                $provider = $providers->provider($key);
                $enum = Provider::tryFrom($key);

                return [
                    'key' => $key,
                    'label' => $enum?->label() ?? ucfirst($key),
                    'capabilities' => $provider->capabilities()->toArray(),
                    'configured' => ! empty(config("social.providers.{$key}.client_id")),
                ];
            })
            ->values();

        return Inertia::render('social/accounts', [
            'accounts' => $accounts,
            'providerCatalog' => $providerCatalog,
            'canManageAccounts' => Gate::allows('create', [SocialAccount::class, $workspace]),
        ]);
    }

    public function redirect(Request $request, string $provider, ProviderManager $providers): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();
        Gate::authorize('create', [SocialAccount::class, $workspace]);

        $stateToken = Str::random(40);
        $codeVerifier = Str::random(64);

        $authState = new AuthState(
            state: $stateToken,
            workspaceId: $workspace->id,
            userId: $user->id,
            provider: $provider,
            redirectUri: route('social.accounts.callback', ['provider' => $provider]),
            codeVerifier: $codeVerifier,
        );

        $request->session()->put('social_oauth_state', [
            'state' => $authState->state,
            'workspace_id' => $authState->workspaceId,
            'user_id' => $authState->userId,
            'provider' => $authState->provider,
            'redirect_uri' => $authState->redirectUri,
            'code_verifier' => $authState->codeVerifier,
        ]);

        $url = $providers->provider($provider)->authorizationUrl($authState);

        return redirect()->away($url);
    }

    public function callback(
        Request $request,
        string $provider,
        ProviderManager $providers,
        ConnectAccount $connectAccount,
    ): RedirectResponse {
        $stored = $request->session()->pull('social_oauth_state');
        $incomingState = (string) $request->query('state', '');

        if (
            ! is_array($stored)
            || ($stored['provider'] ?? null) !== $provider
            || ! hash_equals((string) ($stored['state'] ?? ''), $incomingState)
        ) {
            return redirect()->route('social.accounts.index')->with('error', 'Invalid OAuth state.');
        }

        $user = $request->user();
        abort_if($user === null || $user->id !== (int) ($stored['user_id'] ?? 0), 403);

        $workspace = $user->resolveCurrentWorkspace();
        Gate::authorize('create', [SocialAccount::class, $workspace]);

        $authState = new AuthState(
            state: (string) $stored['state'],
            workspaceId: (int) $stored['workspace_id'],
            userId: (int) $stored['user_id'],
            provider: $provider,
            redirectUri: (string) $stored['redirect_uri'],
            codeVerifier: $stored['code_verifier'] ?? null,
        );

        $result = $providers->provider($provider)->handleCallback($request->query(), $authState);

        foreach ($result->accounts as $discovered) {
            $connectAccount->handle($workspace, $provider, $discovered);
        }

        return redirect()->route('social.accounts.index')
            ->with('status', count($result->accounts).' account(s) connected.');
    }

    public function connectSandbox(Request $request, ConnectAccount $connectAccount): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();
        Gate::authorize('create', [SocialAccount::class, $workspace]);

        $validated = $request->validate([
            'provider' => ['required', Rule::in(['facebook', 'instagram', 'threads', 'linkedin', 'youtube', 'fake'])],
            'display_name' => ['required', 'string', 'max:120'],
            'handle' => ['nullable', 'string', 'max:80'],
        ]);

        $providerKey = $validated['provider'];
        $kind = match ($providerKey) {
            'facebook' => AccountKind::Page,
            'instagram' => AccountKind::Business,
            'linkedin' => AccountKind::Organization,
            'youtube' => AccountKind::Channel,
            default => AccountKind::Profile,
        };

        $connectAccount->handle($workspace, $providerKey, new DiscoveredAccount(
            externalId: 'sandbox_'.$providerKey.'_'.Str::lower(Str::random(8)),
            name: $validated['display_name'],
            kind: $kind,
            tokenSet: new TokenSet(
                accessToken: 'sandbox_token_'.Str::random(24),
                expiresAt: now()->addDays(60),
                scopes: ['sandbox'],
            ),
            username: $validated['handle'] ?? '@'.Str::slug($validated['display_name']),
        ));

        return redirect()->route('social.accounts.index')
            ->with('status', 'Sandbox account connected.');
    }

    public function destroy(SocialAccount $account, DisconnectAccount $disconnectAccount): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $disconnectAccount->handle($account);

        return redirect()->route('social.accounts.index')
            ->with('status', 'Social account disconnected and tokens purged.');
    }
}
