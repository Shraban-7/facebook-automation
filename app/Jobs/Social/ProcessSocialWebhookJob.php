<?php

namespace App\Jobs\Social;

use App\Contracts\Social\SupportsWebhooks;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\SocialAccount;
use App\Models\WebhookEvent;
use App\Services\Social\ProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

class ProcessSocialWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $webhookEventId,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(ProviderManager $providers): void
    {
        $eventRecord = WebhookEvent::query()->find($this->webhookEventId);
        if (! $eventRecord || $eventRecord->status === 'processed') {
            return;
        }

        $providerName = (string) ($eventRecord->provider ?? 'facebook');
        $provider = $providers->provider($providerName);

        if ($provider instanceof SupportsWebhooks) {
            /** @var array<string, mixed> $payload */
            $payload = is_array($eventRecord->payload) ? $eventRecord->payload : [];
            $syntheticRequest = Request::create('/', 'POST', $payload);
            $normalizedEvents = $provider->normalize($syntheticRequest);

            foreach ($normalizedEvents as $normalized) {
                $account = SocialAccount::query()
                    ->where('provider', $normalized->provider)
                    ->where('external_id', $normalized->accountExternalId)
                    ->first();

                if (! $account) {
                    continue;
                }

                if ($normalized->type === 'comment.created' || $normalized->type === 'comment') {
                    Comment::query()->updateOrCreate(
                        [
                            'social_account_id' => $account->id,
                            'external_id' => $normalized->eventId,
                        ],
                        [
                            'author_name' => $normalized->actorName,
                            'author_external_id' => $normalized->actorExternalId ?? 'unknown',
                            'body' => $normalized->text ?? '',
                            'created_at_platform' => $normalized->occurredAt,
                        ]
                    );
                } elseif ($normalized->type === 'message.received' || $normalized->type === 'message') {
                    $threadId = (string) ($normalized->payload['thread_id'] ?? $normalized->actorExternalId ?? $normalized->eventId);
                    $conversation = Conversation::query()->firstOrCreate(
                        [
                            'social_account_id' => $account->id,
                            'external_id' => $threadId,
                        ],
                        [
                            'workspace_id' => $account->workspace_id,
                            'participant_name' => $normalized->actorName ?? 'Contact',
                            'participant_external_id' => $normalized->actorExternalId ?? $threadId,
                            'status' => 'open',
                        ]
                    );

                    InboxMessage::query()->firstOrCreate(
                        [
                            'conversation_id' => $conversation->id,
                            'external_id' => $normalized->eventId,
                        ],
                        [
                            'direction' => 'inbound',
                            'body' => $normalized->text,
                            'delivered_at' => $normalized->occurredAt,
                            'status' => 'received',
                        ]
                    );

                    $conversation->increment('unread_count');
                    $conversation->update(['last_message_at' => $normalized->occurredAt]);
                }
            }
        }

        $eventRecord->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
