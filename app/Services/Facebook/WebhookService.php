<?php

namespace App\Services\Facebook;

use App\Jobs\ProcessWebhookEventJob;
use App\Models\FacebookPage;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;

class WebhookService
{
    public function __construct(
        private readonly ActivityLogService $activityLogger,
        private readonly AutoReplyService $autoReplyService,
    ) {}

    /**
     * Validate Meta's webhook verification handshake (`hub.mode`, `hub.verify_token`, `hub.challenge`).
     */
    public function verifyHandshake(?string $mode, ?string $verifyToken, ?string $challenge): ?string
    {
        $expectedToken = (string) config('facebook.webhook_verify_token', '');

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, (string) $verifyToken)) {
            $this->activityLogger->record([
                'category' => 'webhook',
                'action' => 'webhook.handshake.verified',
                'level' => 'info',
                'method' => 'GET',
                'endpoint' => '/api/webhooks/facebook',
                'http_status' => 200,
                'message' => 'Meta webhook verification handshake succeeded.',
            ]);

            return (string) $challenge;
        }

        $this->activityLogger->record([
            'category' => 'webhook',
            'action' => 'webhook.handshake.rejected',
            'level' => 'warning',
            'method' => 'GET',
            'endpoint' => '/api/webhooks/facebook',
            'http_status' => 403,
            'message' => 'Meta webhook verification handshake rejected due to invalid verify token.',
            'context' => ['mode' => $mode],
        ]);

        return null;
    }

    /**
     * Verify `X-Hub-Signature-256` against the raw HTTP request body using the App Secret.
     */
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $appSecret = (string) config('facebook.app_secret', '');

        if ($appSecret === '') {
            return false;
        }

        $expectedHash = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expectedHash, $signatureHeader);
    }

    /**
     * Ingest a verified Meta webhook payload idempotently and dispatch background processing jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array{queued_count: int, duplicate_count: int, event_ids: array<int, string>}
     */
    public function ingestPayload(array $payload, ?string $signatureHeader, bool $signatureVerified): array
    {
        $object = (string) ($payload['object'] ?? 'page');
        /** @var array<int, array<string, mixed>> $entries */
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

        $queuedCount = 0;
        $duplicateCount = 0;
        $eventIds = [];

        foreach ($entries as $entry) {
            $pageExternalId = (string) ($entry['id'] ?? 'unknown');
            $page = FacebookPage::query()->where('page_id', $pageExternalId)->first();

            $normalizedEvents = $this->extractIndividualEvents($object, $pageExternalId, $entry);

            foreach ($normalizedEvents as $item) {
                $existing = WebhookEvent::query()->where('event_id', $item['event_id'])->first();

                if ($existing !== null) {
                    $duplicateCount++;

                    continue;
                }

                $webhookEvent = WebhookEvent::create([
                    'facebook_page_id' => $page?->id,
                    'event_id' => $item['event_id'],
                    'object' => $object,
                    'entry_id' => $pageExternalId,
                    'event_type' => $item['event_type'],
                    'signature' => $signatureHeader,
                    'signature_verified' => $signatureVerified,
                    'payload' => $item['payload'],
                    'status' => 'received',
                ]);

                $this->activityLogger->record([
                    'user_id' => $page?->user_id,
                    'facebook_page_id' => $page?->id,
                    'category' => 'webhook',
                    'action' => 'webhook.event.received',
                    'level' => 'info',
                    'method' => 'POST',
                    'endpoint' => '/api/webhooks/facebook',
                    'http_status' => 200,
                    'message' => sprintf('Webhook event [%s] queued (ID: %s)', $item['event_type'], $item['event_id']),
                    'context' => ['event_id' => $item['event_id'], 'entry_id' => $pageExternalId],
                ]);

                ProcessWebhookEventJob::dispatch($webhookEvent->id);

                $queuedCount++;
                $eventIds[] = $item['event_id'];
            }
        }

        return [
            'queued_count' => $queuedCount,
            'duplicate_count' => $duplicateCount,
            'event_ids' => $eventIds,
        ];
    }

    /**
     * Process a single persisted `WebhookEvent` inside a queued job.
     */
    public function processStoredEvent(WebhookEvent $webhookEvent): void
    {
        if ($webhookEvent->status === 'processed') {
            return;
        }

        $webhookEvent->update([
            'status' => 'processing',
            'attempts' => $webhookEvent->attempts + 1,
        ]);

        $page = $webhookEvent->facebookPage
            ?? FacebookPage::query()->where('page_id', $webhookEvent->entry_id)->first();

        if ($page === null) {
            $webhookEvent->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'No connected FacebookPage matched entry_id '.$webhookEvent->entry_id,
            ]);

            return;
        }

        $payload = $webhookEvent->payload;

        if ($webhookEvent->event_type === 'messages') {
            $isEcho = (bool) ($payload['message']['is_echo'] ?? false);

            if ($isEcho) {
                $webhookEvent->update([
                    'status' => 'ignored',
                    'processed_at' => now(),
                ]);

                return;
            }

            $senderPsid = (string) ($payload['sender']['id'] ?? '');
            $messageText = (string) ($payload['message']['text'] ?? '');
            $mid = isset($payload['message']['mid']) ? (string) $payload['message']['mid'] : $webhookEvent->event_id;
            $timestampMs = isset($payload['timestamp']) ? (int) $payload['timestamp'] : null;
            $interactedAt = $timestampMs !== null && $timestampMs > 0
                ? Carbon::createFromTimestampMs($timestampMs)
                : now();

            if ($senderPsid !== '' && $messageText !== '') {
                $this->autoReplyService->handleInboundMessenger(
                    page: $page,
                    senderPsid: $senderPsid,
                    messageText: $messageText,
                    externalId: $mid,
                    senderName: isset($payload['sender']['name']) ? (string) $payload['sender']['name'] : null,
                    interactedAt: $interactedAt,
                );
            }
        } elseif ($webhookEvent->event_type === 'feed_comment') {
            $value = is_array($payload['value'] ?? null) ? $payload['value'] : $payload;
            $verb = (string) ($value['verb'] ?? 'add');

            if ($verb === 'add') {
                $commentId = (string) ($value['comment_id'] ?? $webhookEvent->event_id);
                $postId = isset($value['post_id']) ? (string) $value['post_id'] : null;
                $senderId = (string) ($value['from']['id'] ?? '');
                $senderName = isset($value['from']['name']) ? (string) $value['from']['name'] : null;
                $commentText = (string) ($value['message'] ?? '');

                if ($senderId !== '' && $commentText !== '') {
                    $this->autoReplyService->handleInboundComment(
                        page: $page,
                        commentId: $commentId,
                        senderId: $senderId,
                        commentText: $commentText,
                        postId: $postId,
                        senderName: $senderName,
                    );
                }
            }
        }

        $webhookEvent->update([
            'facebook_page_id' => $page->id,
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Extract individual messaging or feed changes from an entry and compute a deterministic `event_id`.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, array{event_id: string, event_type: string, payload: array<string, mixed>}>
     */
    private function extractIndividualEvents(string $object, string $pageExternalId, array $entry): array
    {
        $results = [];

        if (is_array($entry['messaging'] ?? null)) {
            foreach ($entry['messaging'] as $messagingItem) {
                if (! is_array($messagingItem)) {
                    continue;
                }

                $mid = isset($messagingItem['message']['mid'])
                    ? (string) $messagingItem['message']['mid']
                    : null;

                $eventType = isset($messagingItem['postback']) ? 'messaging_postbacks' : 'messages';
                $eventId = $mid !== null && $mid !== ''
                    ? 'msg_'.$mid
                    : 'msg_'.hash('sha256', $pageExternalId.':'.json_encode($messagingItem));

                $results[] = [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'payload' => $messagingItem,
                ];
            }
        }

        if (is_array($entry['changes'] ?? null)) {
            foreach ($entry['changes'] as $changeItem) {
                if (! is_array($changeItem)) {
                    continue;
                }

                $field = (string) ($changeItem['field'] ?? 'feed');
                $value = is_array($changeItem['value'] ?? null) ? $changeItem['value'] : [];
                $itemType = (string) ($value['item'] ?? '');
                $verb = (string) ($value['verb'] ?? 'add');

                $eventType = ($field === 'feed' && $itemType === 'comment') ? 'feed_comment' : 'feed_'.$field;

                $commentId = isset($value['comment_id']) ? (string) $value['comment_id'] : null;
                $createdTime = isset($value['created_time']) ? (string) $value['created_time'] : '';

                $eventId = $commentId !== null && $commentId !== ''
                    ? sprintf('chg_%s_%s_%s', $commentId, $verb, $createdTime)
                    : 'chg_'.hash('sha256', $pageExternalId.':'.json_encode($changeItem));

                $results[] = [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'payload' => $changeItem,
                ];
            }
        }

        if ($results === []) {
            $results[] = [
                'event_id' => 'evt_'.hash('sha256', $object.':'.$pageExternalId.':'.json_encode($entry)),
                'event_type' => 'unknown',
                'payload' => $entry,
            ];
        }

        return $results;
    }
}
