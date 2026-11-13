<?php

namespace App\Http\Controllers\Social;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StoreWorkspaceMemberRequest;
use App\Models\PublishAttempt;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Social\CircuitBreaker;
use App\Services\Social\SocialRateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('view', $workspace);

        $user = $request->user();
        abort_if($user === null, 401);

        $user->update(['active_workspace_id' => $workspace->id]);

        return back()->with('status', "Switched to workspace {$workspace->name}.");
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();

        $members = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->with('user')
            ->get()
            ->map(fn (WorkspaceMember $m): array => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->role,
                'is_owner' => $m->user_id === $workspace->owner_id,
            ]);

        return Inertia::render('settings/workspace', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'plan' => $workspace->plan,
            ],
            'members' => $members,
            'canManageMembers' => Gate::allows('manageMembers', $workspace),
        ]);
    }

    public function addMember(StoreWorkspaceMemberRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();
        Gate::authorize('manageMembers', $workspace);

        $invitee = User::query()->where('email', $request->validated('email'))->first();
        if (! $invitee) {
            return back()->withErrors([
                'email' => 'No registered user found with that email address.',
            ]);
        }

        WorkspaceMember::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $invitee->id,
            ],
            [
                'role' => WorkspaceRole::from((string) $request->validated('role'))->value,
                'invited_by' => $user->id,
                'accepted_at' => now(),
            ]
        );

        return back()->with('status', 'Workspace member updated.');
    }

    public function removeMember(WorkspaceMember $member): RedirectResponse
    {
        $workspace = $member->workspace;
        Gate::authorize('manageMembers', $workspace);

        if ($member->user_id === $workspace->owner_id) {
            return back()->withErrors(['member' => 'Cannot remove workspace owner.']);
        }

        $member->delete();

        return back()->with('status', 'Workspace member removed.');
    }

    public function operations(
        Request $request,
        CircuitBreaker $circuitBreaker,
        SocialRateLimiter $rateLimiter,
    ): Response {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();

        $providerHealth = [];
        foreach (['facebook', 'instagram', 'threads', 'linkedin', 'youtube'] as $key) {
            $status = $circuitBreaker->status($key);
            $quota = $key === 'youtube'
                ? $rateLimiter->getDailyQuotaStatus('youtube', 'default')
                : null;

            $providerHealth[] = [
                'provider' => $key,
                'open' => $status['state'] === 'open',
                'failures' => $status['failures'],
                'quota' => $quota,
            ];
        }

        $attempts = PublishAttempt::query()
            ->whereHas('postTarget.content', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with('postTarget.socialAccount')
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (PublishAttempt $a): array => [
                'id' => $a->id,
                'provider' => $a->postTarget->socialAccount->provider,
                'account_name' => $a->postTarget->socialAccount->name,
                'attempt_no' => $a->attempt_no,
                'status' => $a->outcome,
                'error_class' => $a->error_class,
                'error_message' => $a->response_excerpt,
                'duration_ms' => null,
                'created_at' => $a->started_at->toIso8601String(),
            ]);

        $webhooks = WebhookEvent::query()
            ->latest('id')
            ->limit(20)
            ->get(['id', 'provider', 'event_type', 'status', 'created_at'])
            ->map(fn (WebhookEvent $e): array => [
                'id' => $e->id,
                'provider' => $e->provider ?? 'facebook',
                'event_type' => $e->event_type,
                'status' => $e->status,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        return Inertia::render('social/operations', [
            'providerHealth' => $providerHealth,
            'attempts' => $attempts,
            'webhooks' => $webhooks,
        ]);
    }
}
