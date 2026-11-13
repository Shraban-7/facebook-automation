<?php

namespace App\Services\Social;

use App\Enums\AccountKind;
use App\Enums\PostStatus;
use App\Enums\Provider;
use App\Models\Comment;
use App\Models\Content;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\MetricDefinition;
use App\Models\MetricSnapshot;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class MultiPlatformSeederService
{
    public function seedMetricDefinitions(): void
    {
        $definitions = [
            ['key' => 'impressions', 'label' => 'Impressions', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'reach', 'label' => 'Reach', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'engagements', 'label' => 'Engagements', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'likes', 'label' => 'Likes', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'comments', 'label' => 'Comments', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'shares', 'label' => 'Shares & Reposts', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'clicks', 'label' => 'Link Clicks', 'unit' => 'count', 'aggregation' => 'sum'],
            ['key' => 'followers_total', 'label' => 'Followers / Subscribers', 'unit' => 'count', 'aggregation' => 'last'],
            ['key' => 'video_views', 'label' => 'Video Views', 'unit' => 'count', 'aggregation' => 'sum'],
        ];

        foreach ($definitions as $def) {
            MetricDefinition::query()->updateOrCreate(
                ['key' => $def['key']],
                $def
            );
        }
    }

    public function seedWorkspaceForUser(User $user): Workspace
    {
        $this->seedMetricDefinitions();

        $workspace = $user->resolveCurrentWorkspace();

        if ($workspace->socialAccounts()->exists()) {
            return $workspace;
        }

        $accounts = [
            [
                'provider' => Provider::Facebook->value,
                'external_id' => 'demo_fb_page_101',
                'kind' => AccountKind::Page->value,
                'name' => 'Acme Studio Facebook',
                'username' => '@acmestudio',
            ],
            [
                'provider' => Provider::Instagram->value,
                'external_id' => 'demo_ig_biz_202',
                'kind' => AccountKind::Business->value,
                'name' => 'Acme Studio IG',
                'username' => '@acmestudio.ig',
            ],
            [
                'provider' => Provider::Threads->value,
                'external_id' => 'demo_threads_303',
                'kind' => AccountKind::Profile->value,
                'name' => 'Acme Threads',
                'username' => '@acmestudio',
            ],
            [
                'provider' => Provider::LinkedIn->value,
                'external_id' => 'urn:li:organization:404040',
                'kind' => AccountKind::Organization->value,
                'name' => 'Acme Corp LinkedIn',
                'username' => 'acme-corp',
            ],
            [
                'provider' => Provider::YouTube->value,
                'external_id' => 'UC_demo_youtube_505',
                'kind' => AccountKind::Channel->value,
                'name' => 'Acme Studio TV',
                'username' => '@AcmeStudioTV',
            ],
        ];

        $createdAccounts = [];
        foreach ($accounts as $data) {
            $createdAccounts[] = SocialAccount::query()->create(array_merge($data, [
                'workspace_id' => $workspace->id,
                'status' => 'active',
                'last_synced_at' => now(),
            ]));
        }

        $content = Content::query()->create([
            'workspace_id' => $workspace->id,
            'author_id' => $user->id,
            'title' => 'Q4 Multi-Platform Product Launch',
            'body' => 'Introducing our unified creator workflow across Facebook, Instagram, Threads, LinkedIn, and YouTube.',
            'status' => PostStatus::Published->value,
        ]);

        foreach ($createdAccounts as $idx => $account) {
            PostTarget::query()->create([
                'content_id' => $content->id,
                'social_account_id' => $account->id,
                'idempotency_key' => (string) Str::ulid(),
                'status' => PostStatus::Published->value,
                'external_id' => 'demo_post_'.($idx + 1),
                'published_at' => now()->subDays(2),
            ]);

            foreach (range(0, 6) as $daysAgo) {
                $date = now()->subDays($daysAgo)->toDateString();
                MetricSnapshot::query()->create([
                    'social_account_id' => $account->id,
                    'metric_key' => 'impressions',
                    'date' => $date,
                    'value' => random_int(800, 4500),
                ]);
                MetricSnapshot::query()->create([
                    'social_account_id' => $account->id,
                    'metric_key' => 'engagements',
                    'date' => $date,
                    'value' => random_int(60, 420),
                ]);
                MetricSnapshot::query()->create([
                    'social_account_id' => $account->id,
                    'metric_key' => 'followers_total',
                    'date' => $date,
                    'value' => 2500 + ($idx * 700) + ((6 - $daysAgo) * 14),
                ]);
            }
        }

        $conv = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'social_account_id' => $createdAccounts[0]->id,
            'external_id' => 'demo_thread_1',
            'participant_name' => 'Sarah Jenkins',
            'participant_external_id' => 'demo_psid_1',
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => now()->subMinutes(15),
        ]);

        InboxMessage::query()->create([
            'conversation_id' => $conv->id,
            'external_id' => 'demo_msg_1',
            'direction' => 'inbound',
            'body' => 'Hi! Is the new multi-platform scheduler available on the Pro plan?',
            'delivered_at' => now()->subMinutes(15),
            'status' => 'received',
        ]);

        Comment::query()->create([
            'social_account_id' => $createdAccounts[2]->id,
            'external_id' => 'demo_cmt_1',
            'author_external_id' => 'demo_author_1',
            'author_name' => 'Alex Rivera',
            'body' => 'Love seeing Threads and LinkedIn in the same queue!',
            'created_at_platform' => now()->subHours(3),
        ]);

        return $workspace;
    }
}
