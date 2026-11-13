<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\SchedulePost;
use App\Enums\PostStatus;
use App\Enums\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StoreContentRequest;
use App\Jobs\Social\PublishPostTargetJob;
use App\Models\Content;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Services\Social\ProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ComposerController extends Controller
{
    public function index(Request $request, ProviderManager $providers): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();

        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('provider')
            ->get()
            ->map(function (SocialAccount $account) use ($providers): array {
                $enum = Provider::tryFrom($account->provider);

                return [
                    'id' => $account->id,
                    'provider' => $account->provider,
                    'provider_label' => $enum?->label() ?? ucfirst($account->provider),
                    'display_name' => $account->name,
                    'handle' => $account->username,
                    'capabilities' => $providers->provider($account->provider)->capabilities()->toArray(),
                ];
            });

        $contents = Content::query()
            ->where('workspace_id', $workspace->id)
            ->with(['targets.socialAccount', 'mediaAssets'])
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (Content $content): array => [
                'id' => $content->id,
                'title' => $content->title,
                'body' => $content->body,
                'link_url' => $content->link_url,
                'status' => $content->status,
                'scheduled_at' => $content->scheduled_at?->toIso8601String(),
                'created_at' => $content->created_at?->toIso8601String(),
                'targets' => $content->targets->map(fn ($target): array => [
                    'id' => $target->id,
                    'social_account_id' => $target->social_account_id,
                    'provider' => $target->socialAccount->provider,
                    'account_name' => $target->socialAccount->name,
                    'status' => $target->status,
                    'scheduled_at' => $target->scheduled_at?->toIso8601String(),
                    'published_at' => $target->published_at?->toIso8601String(),
                    'permalink' => $target->external_url,
                    'last_error_message' => $target->error_message,
                    'attempt_count' => $target->attempts,
                ]),
            ]);

        $mediaAssets = MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'original_name', 'mime', 'disk', 'path']);

        return Inertia::render('social/composer', [
            'accounts' => $accounts,
            'contents' => $contents,
            'mediaAssets' => $mediaAssets,
            'canPublish' => Gate::allows('create', [Content::class, $workspace]),
        ]);
    }

    public function store(StoreContentRequest $request, SchedulePost $schedulePost): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();
        Gate::authorize('create', [Content::class, $workspace]);

        $schedulePost->handle($workspace, $user, $request->validated());

        return redirect()->route('social.composer.index')
            ->with('status', 'Post scheduled across selected targets.');
    }

    public function approve(Content $content): RedirectResponse
    {
        Gate::authorize('approve', $content);

        $content->load('targets');

        foreach ($content->targets as $target) {
            if ($target->status !== PostStatus::PendingApproval->value) {
                continue;
            }

            if ($target->scheduled_at && $target->scheduled_at->isFuture()) {
                $target->update(['status' => PostStatus::Scheduled->value]);
            } else {
                $target->update(['status' => PostStatus::Queued->value]);
                PublishPostTargetJob::dispatch($target->id);
            }
        }

        $content->update([
            'status' => $content->scheduled_at && $content->scheduled_at->isFuture()
                ? PostStatus::Scheduled->value
                : PostStatus::Queued->value,
        ]);

        return redirect()->route('social.composer.index')
            ->with('status', 'Content approved.');
    }

    public function destroy(Content $content): RedirectResponse
    {
        Gate::authorize('delete', $content);

        $content->delete();

        return redirect()->route('social.composer.index')
            ->with('status', 'Content deleted.');
    }
}
