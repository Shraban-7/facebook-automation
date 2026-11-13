<?php

namespace App\Contracts\Social;

use App\Models\Conversation;
use App\Models\InboxMessage;

interface SupportsMessaging
{
    public function sendMessage(Conversation $conversation, string $body): InboxMessage;
}
