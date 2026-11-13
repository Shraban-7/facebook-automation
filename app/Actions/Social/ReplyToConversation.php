<?php

namespace App\Actions\Social;

use App\Contracts\Social\SupportsMessaging;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\User;
use App\Services\Social\ProviderManager;

class ReplyToConversation
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {}

    public function handle(Conversation $conversation, User $sender, string $body): InboxMessage
    {
        $account = $conversation->socialAccount;
        $provider = $this->providers->provider($account->provider);

        if ($provider instanceof SupportsMessaging) {
            $message = $provider->sendMessage($conversation, $body);
            $conversation->update([
                'last_message_at' => now(),
                'unread_count' => 0,
            ]);

            return $message;
        }

        $message = InboxMessage::query()->create([
            'conversation_id' => $conversation->id,
            'external_id' => 'local_'.uniqid('', true),
            'direction' => 'outbound',
            'sent_by_user_id' => $sender->id,
            'body' => $body,
            'delivered_at' => now(),
            'status' => 'sent',
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => 0,
        ]);

        return $message;
    }
}
