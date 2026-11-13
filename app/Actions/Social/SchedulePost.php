<?php

namespace App\Actions\Social;

use App\Enums\PostStatus;
use App\Enums\Provider;
use App\Jobs\Social\PublishPostTargetJob;
use App\Models\Content;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\ProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchedulePost
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Workspace $workspace, User $author, array $data): Content
    {
        $requireApproval = (bool) ($data['require_approval'] ?? false);
        /** @var array<int, array<string, mixed>> $targetsInput */
        $targetsInput = is_array($data['targets'] ?? null) ? array_values($data['targets']) : [];

        /** @var Content $content */
        $content = DB::transaction(function () use ($workspace, $author, $data, $targetsInput, $requireApproval): Content {
            $scheduledDates = [];
            foreach ($targetsInput as $t) {
                if (! empty($t['scheduled_at'])) {
                    $scheduledDates[] = CarbonImmutable::parse((string) $t['scheduled_at'])->utc();
                }
            }
            $hasFutureSchedule = false;
            foreach ($scheduledDates as $dt) {
                if ($dt->isFuture()) {
                    $hasFutureSchedule = true;
                    break;
                }
            }

            $initialStatus = match (true) {
                $requireApproval => PostStatus::PendingApproval,
                $hasFutureSchedule => PostStatus::Scheduled,
                default => PostStatus::Queued,
            };

            $content = Content::query()->create([
                'workspace_id' => $workspace->id,
                'author_id' => $author->id,
                'title' => isset($data['title']) ? (string) $data['title'] : null,
                'body' => (string) ($data['body'] ?? ''),
                'link_url' => isset($data['link_url']) ? (string) $data['link_url'] : null,
                'status' => $initialStatus->value,
                'scheduled_at' => $hasFutureSchedule ? min($scheduledDates) : null,
            ]);

            if (! empty($data['media_ids']) && is_array($data['media_ids'])) {
                $syncData = [];
                foreach (array_values($data['media_ids']) as $pos => $mediaId) {
                    $syncData[(int) $mediaId] = ['position' => $pos];
                }
                $content->mediaAssets()->sync($syncData);
            }

            $content->load('mediaAssets');

            foreach ($targetsInput as $targetData) {
                /** @var SocialAccount $account */
                $account = SocialAccount::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey((int) $targetData['social_account_id'])
                    ->firstOrFail();

                $scheduledAt = ! empty($targetData['scheduled_at'])
                    ? CarbonImmutable::parse((string) $targetData['scheduled_at'])->utc()
                    : null;

                $targetStatus = match (true) {
                    $requireApproval => PostStatus::PendingApproval,
                    $scheduledAt !== null && $scheduledAt->isFuture() => PostStatus::Scheduled,
                    default => PostStatus::Queued,
                };

                $target = PostTarget::query()->create([
                    'content_id' => $content->id,
                    'social_account_id' => $account->id,
                    'idempotency_key' => (string) Str::ulid(),
                    'status' => $targetStatus->value,
                    'scheduled_at' => $scheduledAt,
                    'body_override' => isset($targetData['body_override']) ? (string) $targetData['body_override'] : null,
                    'options' => is_array($targetData['options'] ?? null) ? $targetData['options'] : [],
                ]);

                $target->setRelation('content', $content);
                $target->setRelation('socialAccount', $account);

                $validation = $this->providers->provider($account->provider)->validate($target);
                if (! $validation->valid) {
                    $providerLabel = Provider::tryFrom($account->provider)?->label() ?? ucfirst($account->provider);
                    throw ValidationException::withMessages([
                        'targets' => ["[{$providerLabel} - {$account->name}] ".implode(' ', $validation->errors)],
                    ]);
                }
            }

            return $content->load(['targets.socialAccount', 'mediaAssets']);
        });

        if (! $requireApproval) {
            foreach ($content->targets as $target) {
                if ($target->status === PostStatus::Queued->value) {
                    PublishPostTargetJob::dispatch($target->id);
                }
            }
        }

        return $content;
    }
}
