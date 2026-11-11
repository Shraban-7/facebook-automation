<?php

namespace Tests\Feature\Facebook;

use App\Jobs\ModerateCommentJob;
use App\Jobs\ProcessWebhookEventJob;
use App\Jobs\SendAutoReplyJob;
use App\Models\AutoReplyRule;
use App\Models\FacebookPage;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Facebook\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'facebook.app_id' => 'test-app-id',
            'facebook.app_secret' => 'test-webhook-secret',
            'facebook.webhook_verify_token' => 'my-secret-verify-token',
            'facebook.graph_version' => 'v21.0',
        ]);
    }

    public function test_webhook_verification_handshake_succeeds_with_valid_verify_token(): void
    {
        $response = $this->get('/api/webhooks/facebook?hub.mode=subscribe&hub.verify_token=my-secret-verify-token&hub.challenge=192837465');

        $response->assertOk();
        $this->assertSame('192837465', $response->getContent());
    }

    public function test_webhook_verification_handshake_rejects_invalid_token(): void
    {
        $response = $this->get('/api/webhooks/facebook?hub.mode=subscribe&hub.verify_token=wrong-token&hub.challenge=192837465');

        $response->assertForbidden();
    }

    public function test_webhook_post_rejects_invalid_signature(): void
    {
        $payload = ['object' => 'page', 'entry' => []];

        $response = $this->postJson('/api/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => 'sha256=invalidsignature',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_post_is_idempotent_and_deduplicates_by_event_id(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        FacebookPage::create([
            'user_id' => $user->id,
            'page_id' => '100200300',
            'name' => 'Test Brand Page',
            'access_token' => 'secret-page-token',
            'status' => 'active',
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '100200300',
                    'time' => 1730000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'psid_998877'],
                            'recipient' => ['id' => '100200300'],
                            'timestamp' => now()->getTimestampMs(),
                            'message' => [
                                'mid' => 'm_unique_mid_001',
                                'text' => 'What is the price?',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $rawJson, 'test-webhook-secret');

        // 1st delivery: queued
        $firstResponse = $this->call(
            'POST',
            '/api/webhooks/facebook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            $rawJson
        );

        $firstResponse->assertOk()
            ->assertJson([
                'status' => 'EVENT_RECEIVED',
                'queued' => 1,
                'duplicates_ignored' => 0,
            ]);

        // 2nd identical delivery: deduplicated
        $secondResponse = $this->call(
            'POST',
            '/api/webhooks/facebook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            $rawJson
        );

        $secondResponse->assertOk()
            ->assertJson([
                'status' => 'EVENT_RECEIVED',
                'queued' => 0,
                'duplicates_ignored' => 1,
            ]);

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWebhookEventJob::class, 1);
    }

    public function test_auto_reply_matches_keyword_respects_24h_window_and_moderates_banned_comments(): void
    {
        Queue::fake([SendAutoReplyJob::class, ModerateCommentJob::class]);
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $page = FacebookPage::create([
            'user_id' => $user->id,
            'page_id' => '100200300',
            'name' => 'Studio Page',
            'access_token' => 'secret-page-token',
            'status' => 'active',
        ]);

        AutoReplyRule::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'name' => 'Price Rule',
            'channel' => 'both',
            'trigger_type' => 'keyword',
            'keywords' => ['price', 'cost'],
            'reply_template' => 'Hi {{sender_name}}, our catalog starts at $99.',
            'moderation_action' => 'none',
            'respect_24h_window' => true,
            'cooldown_seconds' => 60,
            'priority' => 10,
            'is_active' => true,
        ]);

        AutoReplyRule::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'name' => 'Spam Shield',
            'channel' => 'comment',
            'trigger_type' => 'keyword',
            'keywords' => [],
            'banned_words' => ['scam link', 'free crypto'],
            'reply_template' => 'Hidden',
            'moderation_action' => 'hide',
            'respect_24h_window' => true,
            'cooldown_seconds' => 0,
            'priority' => 1,
            'is_active' => true,
        ]);

        // 1. Inbound Messenger within 24h window -> queues SendAutoReplyJob
        $withinWindowEvent = WebhookEvent::create([
            'facebook_page_id' => $page->id,
            'event_id' => 'msg_within_24h',
            'object' => 'page',
            'entry_id' => $page->page_id,
            'event_type' => 'messages',
            'signature_verified' => true,
            'payload' => [
                'sender' => ['id' => 'psid_101', 'name' => 'Sarah'],
                'timestamp' => now()->subHours(2)->getTimestampMs(),
                'message' => ['mid' => 'm_101', 'text' => 'Can you tell me the price?'],
            ],
            'status' => 'received',
        ]);

        app(WebhookService::class)->processStoredEvent($withinWindowEvent);

        $this->assertDatabaseHas('messages', [
            'sender_id' => 'psid_101',
            'status' => 'queued',
            'reply_text' => 'Hi Sarah, our catalog starts at $99.',
        ]);
        Queue::assertPushed(SendAutoReplyJob::class, 1);

        // 2. Inbound Messenger OUTSIDE 24h window (30 hours old) -> blocked by 24h policy
        $outsideWindowEvent = WebhookEvent::create([
            'facebook_page_id' => $page->id,
            'event_id' => 'msg_outside_24h',
            'object' => 'page',
            'entry_id' => $page->page_id,
            'event_type' => 'messages',
            'signature_verified' => true,
            'payload' => [
                'sender' => ['id' => 'psid_202', 'name' => 'David'],
                'timestamp' => now()->subHours(30)->getTimestampMs(),
                'message' => ['mid' => 'm_202', 'text' => 'What is the price?'],
            ],
            'status' => 'received',
        ]);

        app(WebhookService::class)->processStoredEvent($outsideWindowEvent);

        $this->assertDatabaseHas('messages', [
            'sender_id' => 'psid_202',
            'status' => 'skipped_outside_window',
        ]);

        // 3. Inbound Comment with banned words -> queues ModerateCommentJob
        $spamCommentEvent = WebhookEvent::create([
            'facebook_page_id' => $page->id,
            'event_id' => 'chg_spam_comment_1',
            'object' => 'page',
            'entry_id' => $page->page_id,
            'event_type' => 'feed_comment',
            'signature_verified' => true,
            'payload' => [
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => '100200300_555_999',
                    'from' => ['id' => 'user_spammer', 'name' => 'Spammer'],
                    'message' => 'Click my free crypto giveaway now!',
                ],
            ],
            'status' => 'received',
        ]);

        app(WebhookService::class)->processStoredEvent($spamCommentEvent);

        $this->assertDatabaseHas('messages', [
            'external_id' => '100200300_555_999',
            'is_hidden' => true,
            'status' => 'moderated',
        ]);
        Queue::assertPushed(ModerateCommentJob::class, 1);
    }
}
