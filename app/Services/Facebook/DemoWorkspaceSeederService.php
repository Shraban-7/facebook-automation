<?php

namespace App\Services\Facebook;

use App\Models\ActivityLog;
use App\Models\AutoReplyRule;
use App\Models\FacebookPage;
use App\Models\Insight;
use App\Models\Message;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Str;

class DemoWorkspaceSeederService
{
    /**
     * Populate realistic multi-page Facebook automation data for a user.
     */
    public function seedForUser(User $user): FacebookPage
    {
        $primaryPage = FacebookPage::updateOrCreate(
            [
                'user_id' => $user->id,
                'page_id' => '108492019482710',
            ],
            [
                'name' => 'Atelier Forma Studio',
                'category' => 'Design & Architecture',
                'avatar_url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=200&auto=format&fit=crop&q=80',
                'followers_count' => 48920,
                'access_token' => 'EAABsbCS1iHgBO_demo_long_lived_page_token_atelier_forma_99182',
                'user_access_token' => 'EAABsbCS1iHgBO_demo_long_lived_user_token_99182',
                'token_expires_at' => now()->addDays(52),
                'token_last_refreshed_at' => now()->subDays(8),
                'permissions' => config('facebook.scopes'),
                'tasks' => ['MANAGE', 'CREATE_CONTENT', 'MODERATE', 'MESSAGING', 'VIEW_MONETIZATION_INSIGHTS'],
                'webhook_subscribed' => true,
                'status' => 'active',
                'last_error_code' => null,
                'last_error_message' => null,
                'last_synced_at' => now()->subMinutes(14),
            ]
        );

        FacebookPage::updateOrCreate(
            [
                'user_id' => $user->id,
                'page_id' => '109384756102938',
            ],
            [
                'name' => 'Kinfolk Roasters NYC',
                'category' => 'Specialty Coffee & Roastery',
                'avatar_url' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=200&auto=format&fit=crop&q=80',
                'followers_count' => 19340,
                'access_token' => 'EAABsbCS1iHgBO_demo_long_lived_page_token_kinfolk_33812',
                'user_access_token' => 'EAABsbCS1iHgBO_demo_long_lived_user_token_99182',
                'token_expires_at' => now()->addDays(5),
                'token_last_refreshed_at' => now()->subDays(55),
                'permissions' => config('facebook.scopes'),
                'tasks' => ['MANAGE', 'CREATE_CONTENT', 'MODERATE', 'MESSAGING'],
                'webhook_subscribed' => true,
                'status' => 'active',
                'last_synced_at' => now()->subHours(2),
            ]
        );

        if ($user->active_facebook_page_id === null) {
            $user->update(['active_facebook_page_id' => $primaryPage->id]);
        }

        $this->seedPostsAndSchedules($user, $primaryPage);
        $this->seedAutoReplyRulesAndMessages($user, $primaryPage);
        $this->seedInsights($primaryPage);
        $this->seedWebhookEventsAndLogs($user, $primaryPage);

        return $primaryPage;
    }

    private function seedPostsAndSchedules(User $user, FacebookPage $page): void
    {
        if (Post::where('facebook_page_id', $page->id)->exists()) {
            return;
        }

        // 1. Published high-performing posts
        $publishedPosts = [
            [
                'type' => 'image',
                'content' => 'Inside our Autumn/Winter architectural monograph: raw travertine, brushed bronze, and daylight studies across 14 European studios.',
                'media_urls' => ['https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1000&auto=format&fit=crop&q=80'],
                'published_at' => now()->subDays(5)->setTime(14, 30),
                'metrics' => ['reach' => 28450, 'engagements' => 3190, 'reactions' => 2140, 'comments' => 610, 'shares' => 440],
            ],
            [
                'type' => 'link',
                'content' => 'Read our latest case study on modular acoustic timber systems and low-carbon showroom fabrication.',
                'link_url' => 'https://atelierforma.example.com/journal/modular-timber-systems',
                'link_title' => 'Modular Acoustic Timber Systems — 2026 Field Report',
                'published_at' => now()->subDays(11)->setTime(11, 0),
                'metrics' => ['reach' => 19820, 'engagements' => 1875, 'reactions' => 1210, 'comments' => 345, 'shares' => 320],
            ],
            [
                'type' => 'text',
                'content' => 'Open studio hours this Friday in SoHo (10am – 6pm). Come inspect material samples from our Milan collection and meet the lead industrial designers.',
                'published_at' => now()->subDays(2)->setTime(16, 15),
                'metrics' => ['reach' => 14210, 'engagements' => 1490, 'reactions' => 980, 'comments' => 320, 'shares' => 190],
            ],
        ];

        foreach ($publishedPosts as $idx => $item) {
            $fbId = sprintf('%s_%d', $page->page_id, 900100 + $idx);
            $post = Post::create([
                'user_id' => $user->id,
                'facebook_page_id' => $page->id,
                'facebook_post_id' => $fbId,
                'type' => $item['type'],
                'content' => $item['content'],
                'media_urls' => $item['media_urls'] ?? null,
                'link_url' => $item['link_url'] ?? null,
                'link_title' => $item['link_title'] ?? null,
                'status' => 'published',
                'published_at' => $item['published_at'],
                'permalink_url' => 'https://www.facebook.com/'.$fbId,
                'metrics' => $item['metrics'],
            ]);

            ScheduledPost::create([
                'post_id' => $post->id,
                'facebook_page_id' => $page->id,
                'user_id' => $user->id,
                'scheduled_for' => $item['published_at'],
                'timezone' => 'UTC',
                'status' => 'published',
                'attempts' => 1,
                'max_attempts' => 4,
                'last_attempted_at' => $item['published_at'],
                'published_at' => $item['published_at'],
                'facebook_post_id' => $fbId,
                'idempotency_key' => 'sched_demo_pub_'.$post->id,
            ]);
        }

        // 2. Upcoming scheduled posts on the calendar
        $upcoming = [
            [
                'type' => 'image',
                'content' => 'First look: Limited-edition anodized aluminum desk lamp #04. Pre-orders open Thursday at 09:00 EST. Comment "PRICE" for the full spec sheet in Messenger.',
                'media_urls' => ['https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=1000&auto=format&fit=crop&q=80'],
                'scheduled_for' => now()->addDays(1)->setTime(15, 0),
            ],
            [
                'type' => 'link',
                'content' => 'Download our 2026 Commercial Lighting & Spatial Acoustics Specification Guide (PDF + CAD blocks included).',
                'link_url' => 'https://atelierforma.example.com/specs/2026-lighting-guide',
                'link_title' => '2026 Architectural Lighting Specification Guide',
                'scheduled_for' => now()->addDays(3)->setTime(13, 30),
            ],
            [
                'type' => 'text',
                'content' => 'Behind the scenes with our master joiners in Copenhagen: how we finish solid smoked oak without synthetic sealants.',
                'scheduled_for' => now()->addDays(6)->setTime(17, 0),
            ],
        ];

        foreach ($upcoming as $item) {
            $post = Post::create([
                'user_id' => $user->id,
                'facebook_page_id' => $page->id,
                'type' => $item['type'],
                'content' => $item['content'],
                'media_urls' => $item['media_urls'] ?? null,
                'link_url' => $item['link_url'] ?? null,
                'link_title' => $item['link_title'] ?? null,
                'status' => 'scheduled',
            ]);

            ScheduledPost::create([
                'post_id' => $post->id,
                'facebook_page_id' => $page->id,
                'user_id' => $user->id,
                'scheduled_for' => $item['scheduled_for'],
                'timezone' => 'UTC',
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 4,
                'idempotency_key' => 'sched_demo_up_'.$post->id.'_'.Str::random(8),
            ]);
        }

        // 3. Draft post
        Post::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'type' => 'text',
            'content' => '[DRAFT] Winter Residency applications for emerging industrial designers — awaiting final confirmation of jury dates.',
            'status' => 'draft',
        ]);

        // 4. Failed scheduled post with realistic retry failure_log
        $failedPost = Post::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'type' => 'image',
            'content' => 'High-resolution gallery walkthrough from Venice Architecture Biennale.',
            'media_urls' => ['https://images.unsplash.com/photo-1513694203232-719a280e022f?w=1000&auto=format&fit=crop&q=80'],
            'status' => 'failed',
            'last_error' => ' (#4) Application request limit reached. Subcode 80001: Page-level rate limit exceeded.',
        ]);

        ScheduledPost::create([
            'post_id' => $failedPost->id,
            'facebook_page_id' => $page->id,
            'user_id' => $user->id,
            'scheduled_for' => now()->subHours(4),
            'timezone' => 'UTC',
            'status' => 'failed',
            'attempts' => 4,
            'max_attempts' => 4,
            'last_attempted_at' => now()->subHours(3)->subMinutes(40),
            'error_code' => 4,
            'error_message' => '(#4) Application request limit reached. Page-level rate limit exceeded.',
            'failure_log' => [
                [
                    'attempt' => 1,
                    'timestamp' => now()->subHours(4)->toIso8601String(),
                    'graph_code' => 2,
                    'graph_subcode' => null,
                    'http_status' => 503,
                    'category' => 'transient_server_error',
                    'message' => 'An unexpected error has occurred. Please retry your request later.',
                    'remediation' => 'Temporary Meta Graph API instability. Retrying with backoff (15s).',
                    'backoff_seconds' => 15,
                ],
                [
                    'attempt' => 2,
                    'timestamp' => now()->subHours(3)->subMinutes(55)->toIso8601String(),
                    'graph_code' => 4,
                    'graph_subcode' => 80001,
                    'http_status' => 429,
                    'category' => 'rate_limited',
                    'message' => '(#4) Application request limit reached.',
                    'remediation' => 'Graph API quota reached. Backing off for 300s.',
                    'backoff_seconds' => 300,
                ],
            ],
            'idempotency_key' => 'sched_demo_fail_'.$failedPost->id,
        ]);
    }

    private function seedAutoReplyRulesAndMessages(User $user, FacebookPage $page): void
    {
        if (AutoReplyRule::where('facebook_page_id', $page->id)->exists()) {
            return;
        }

        $priceRule = AutoReplyRule::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'name' => 'Pricing & Catalog Inquiry',
            'channel' => 'both',
            'trigger_type' => 'keyword',
            'keywords' => ['price', 'cost', 'how much', 'catalog', 'quote'],
            'banned_words' => [],
            'reply_template' => 'Hi {{sender_name}}! Thanks for reaching out to {{page_name}}. Our 2026 trade pricing and specification sheet is available at https://atelierforma.example.com/pricing — let us know if you need custom dimensions!',
            'moderation_action' => 'none',
            'respect_24h_window' => true,
            'cooldown_seconds' => 300,
            'priority' => 10,
            'is_active' => true,
            'match_count' => 142,
            'last_triggered_at' => now()->subMinutes(18),
        ]);

        $shippingRule = AutoReplyRule::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'name' => 'Lead Time & International Shipping',
            'channel' => 'messenger',
            'trigger_type' => 'keyword',
            'keywords' => ['shipping', 'delivery', 'lead time', 'track'],
            'banned_words' => [],
            'reply_template' => 'Hello {{sender_name}}, made-to-order pieces from {{page_name}} ship worldwide within 3–4 weeks via white-glove freight. Share your order reference and our logistics desk will check the status.',
            'moderation_action' => 'none',
            'respect_24h_window' => true,
            'cooldown_seconds' => 300,
            'priority' => 20,
            'is_active' => true,
            'match_count' => 64,
            'last_triggered_at' => now()->subHours(3),
        ]);

        $moderationRule = AutoReplyRule::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'name' => 'Anti-Spam & Crypto/Scam Link Shield',
            'channel' => 'comment',
            'trigger_type' => 'keyword',
            'keywords' => [],
            'banned_words' => ['crypto giveaway', 'whatsapp me', 'free bitcoin', 'telegram link', 'dm for cheap'],
            'reply_template' => 'Comment hidden automatically by Page moderation policy.',
            'moderation_action' => 'hide',
            'respect_24h_window' => true,
            'cooldown_seconds' => 0,
            'priority' => 1,
            'is_active' => true,
            'match_count' => 29,
            'last_triggered_at' => now()->subHours(1),
        ]);

        $fallbackRule = AutoReplyRule::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'name' => 'Standard Studio Concierge Fallback',
            'channel' => 'messenger',
            'trigger_type' => 'fallback',
            'keywords' => [],
            'banned_words' => [],
            'reply_template' => 'Hi {{sender_name}}, thank you for messaging {{page_name}}. Our architectural consultants are reviewing your note and will reply within business hours (Mon–Fri, 09:00–18:00 EST).',
            'moderation_action' => 'none',
            'respect_24h_window' => true,
            'cooldown_seconds' => 900,
            'priority' => 999,
            'is_active' => true,
            'match_count' => 87,
            'last_triggered_at' => now()->subMinutes(47),
        ]);

        // Seed sample messages showing 24h window enforcement, keyword reply, and comment moderation
        Message::create([
            'facebook_page_id' => $page->id,
            'auto_reply_rule_id' => $priceRule->id,
            'channel' => 'messenger',
            'direction' => 'inbound',
            'external_id' => 'm_mid.$cAAda89120391',
            'sender_id' => '7920194827101',
            'sender_name' => 'Clara Vance',
            'recipient_id' => $page->page_id,
            'message_text' => 'Hi! Could you share the price and trade catalog for the Anodized Desk Lamp #04?',
            'reply_text' => $priceRule->reply_template,
            'status' => 'replied',
            'last_user_interaction_at' => now()->subMinutes(18),
            'replied_at' => now()->subMinutes(18),
        ]);

        Message::create([
            'facebook_page_id' => $page->id,
            'auto_reply_rule_id' => $moderationRule->id,
            'channel' => 'comment',
            'direction' => 'inbound',
            'external_id' => '108492019482710_900100_44102',
            'parent_external_id' => '108492019482710_900100',
            'sender_id' => '8841203918231',
            'sender_name' => 'Promo Bot Account',
            'recipient_id' => $page->page_id,
            'message_text' => 'Join our crypto giveaway right now! WhatsApp me for 10x returns!',
            'is_hidden' => true,
            'status' => 'moderated',
            'last_user_interaction_at' => now()->subHours(1),
            'replied_at' => now()->subHours(1),
        ]);

        Message::create([
            'facebook_page_id' => $page->id,
            'auto_reply_rule_id' => $shippingRule->id,
            'channel' => 'messenger',
            'direction' => 'inbound',
            'external_id' => 'm_mid.$cAAda77231049',
            'sender_id' => '6619283746102',
            'sender_name' => 'Marcus Lindqvist',
            'recipient_id' => $page->page_id,
            'message_text' => 'What is the shipping lead time to Stockholm?',
            'status' => 'skipped_outside_window',
            'error_message' => 'Skipped: Last user interaction (29h ago) exceeds Meta\'s 24-hour standard messaging window.',
            'last_user_interaction_at' => now()->subHours(29),
        ]);

        Message::create([
            'facebook_page_id' => $page->id,
            'auto_reply_rule_id' => $fallbackRule->id,
            'channel' => 'messenger',
            'direction' => 'inbound',
            'external_id' => 'm_mid.$cAAda99481723',
            'sender_id' => '5591827364510',
            'sender_name' => 'Elena Rostova',
            'recipient_id' => $page->page_id,
            'message_text' => 'Do you collaborate with hospitality interior architects on custom acoustic panels?',
            'reply_text' => $fallbackRule->reply_template,
            'status' => 'replied',
            'last_user_interaction_at' => now()->subMinutes(47),
            'replied_at' => now()->subMinutes(47),
        ]);
    }

    private function seedInsights(FacebookPage $page): void
    {
        if (Insight::where('facebook_page_id', $page->id)->count() >= 30) {
            return;
        }

        $baseFollowers = max(1000, $page->followers_count - 1850);

        for ($i = 89; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $wave = sin(($i / 7) * M_PI) * 0.25 + 1;
            $trend = 1 + ((90 - $i) / 180);

            $reach = (int) round(3200 * $wave * $trend + (($i * 137) % 680));
            $impressions = (int) round($reach * 1.38);
            $engaged = (int) round($reach * (0.085 + (($i % 5) * 0.008)));
            $dailyFollows = 12 + ($i % 19);
            $baseFollowers += $dailyFollows;

            Insight::updateOrCreate(
                [
                    'facebook_page_id' => $page->id,
                    'metric_date' => $date,
                    'period' => 'day',
                ],
                [
                    'reach' => $reach,
                    'impressions' => $impressions,
                    'engaged_users' => $engaged,
                    'engagements' => $engaged,
                    'followers' => $baseFollowers,
                    'net_followers' => $dailyFollows,
                    'page_views' => (int) round($reach * 0.21),
                    'reactions' => (int) round($engaged * 0.64),
                    'comments_count' => (int) round($engaged * 0.22),
                    'shares_count' => (int) round($engaged * 0.14),
                ]
            );
        }
    }

    private function seedWebhookEventsAndLogs(User $user, FacebookPage $page): void
    {
        if (WebhookEvent::where('facebook_page_id', $page->id)->exists()) {
            return;
        }

        WebhookEvent::create([
            'facebook_page_id' => $page->id,
            'event_id' => 'msg_m_mid.$cAAda89120391',
            'object' => 'page',
            'entry_id' => $page->page_id,
            'event_type' => 'messages',
            'signature' => 'sha256=8f92e17c4b3a9102d8e4b5120938475610293847561029384756102938475610',
            'signature_verified' => true,
            'payload' => [
                'sender' => ['id' => '7920194827101', 'name' => 'Clara Vance'],
                'recipient' => ['id' => $page->page_id],
                'timestamp' => now()->subMinutes(18)->getTimestampMs(),
                'message' => [
                    'mid' => 'm_mid.$cAAda89120391',
                    'text' => 'Hi! Could you share the price and trade catalog for the Anodized Desk Lamp #04?',
                ],
            ],
            'status' => 'processed',
            'attempts' => 1,
            'processed_at' => now()->subMinutes(18),
        ]);

        WebhookEvent::create([
            'facebook_page_id' => $page->id,
            'event_id' => 'chg_108492019482710_900100_44102_add',
            'object' => 'page',
            'entry_id' => $page->page_id,
            'event_type' => 'feed_comment',
            'signature' => 'sha256=3c71b09e2d1f4401a9c8b7654321098765432109876543210987654321098765',
            'signature_verified' => true,
            'payload' => [
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => '108492019482710_900100_44102',
                    'post_id' => '108492019482710_900100',
                    'from' => ['id' => '8841203918231', 'name' => 'Promo Bot Account'],
                    'message' => 'Join our crypto giveaway right now! WhatsApp me for 10x returns!',
                ],
            ],
            'status' => 'processed',
            'attempts' => 1,
            'processed_at' => now()->subHours(1),
        ]);

        $logs = [
            [
                'category' => 'api_call',
                'action' => 'graph.messenger.send',
                'level' => 'info',
                'method' => 'POST',
                'endpoint' => '/v21.0/'.$page->page_id.'/messages',
                'http_status' => 200,
                'duration_ms' => 142,
                'rate_limit_usage' => ['call_count' => 14, 'total_cputime' => 8, 'total_time' => 11],
                'message' => 'Sent Messenger auto-reply (Rule: Pricing & Catalog Inquiry) to PSID 7920194827101.',
            ],
            [
                'category' => 'moderation',
                'action' => 'graph.comment.moderate',
                'level' => 'warning',
                'method' => 'POST',
                'endpoint' => '/v21.0/108492019482710_900100_44102',
                'http_status' => 200,
                'duration_ms' => 118,
                'rate_limit_usage' => ['call_count' => 13, 'total_cputime' => 7, 'total_time' => 9],
                'message' => 'Hidden comment 108492019482710_900100_44102 matching banned word "crypto giveaway".',
            ],
            [
                'category' => 'webhook',
                'action' => 'webhook.event.received',
                'level' => 'info',
                'method' => 'POST',
                'endpoint' => '/api/webhooks/facebook',
                'http_status' => 200,
                'duration_ms' => 6,
                'message' => 'Verified X-Hub-Signature-256 and queued webhook event msg_m_mid.$cAAda89120391.',
            ],
            [
                'category' => 'error',
                'action' => 'graph.post.publish_image',
                'level' => 'error',
                'method' => 'POST',
                'endpoint' => '/v21.0/'.$page->page_id.'/photos',
                'http_status' => 429,
                'graph_error_code' => 4,
                'graph_error_subcode' => 80001,
                'duration_ms' => 89,
                'rate_limit_usage' => ['call_count' => 82, 'total_cputime' => 45, 'total_time' => 64],
                'message' => 'Graph API error (#4/80001): Application request limit reached. Scheduled backoff retry.',
            ],
            [
                'category' => 'api_call',
                'action' => 'graph.insights.fetch',
                'level' => 'info',
                'method' => 'GET',
                'endpoint' => '/v21.0/'.$page->page_id.'/insights',
                'http_status' => 200,
                'duration_ms' => 264,
                'rate_limit_usage' => ['call_count' => 11, 'total_cputime' => 6, 'total_time' => 9],
                'message' => 'Synchronized 30-day Page Insights (reach, engagement, followers) and warmed Redis cache.',
            ],
        ];

        foreach ($logs as $log) {
            ActivityLog::create([
                'user_id' => $user->id,
                'facebook_page_id' => $page->id,
                ...$log,
            ]);
        }
    }
}
