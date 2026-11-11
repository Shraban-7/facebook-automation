<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $facebook_page_id
 * @property int|null $auto_reply_rule_id
 * @property string $channel
 * @property string $direction
 * @property string|null $external_id
 * @property string|null $parent_external_id
 * @property string $sender_id
 * @property string|null $sender_name
 * @property string|null $recipient_id
 * @property string|null $message_text
 * @property string|null $reply_text
 * @property bool $is_hidden
 * @property string $status
 * @property Carbon|null $last_user_interaction_at
 * @property Carbon|null $replied_at
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'facebook_page_id',
    'auto_reply_rule_id',
    'channel',
    'direction',
    'external_id',
    'parent_external_id',
    'sender_id',
    'sender_name',
    'recipient_id',
    'message_text',
    'reply_text',
    'is_hidden',
    'status',
    'last_user_interaction_at',
    'replied_at',
    'error_message',
    'metadata',
])]
class Message extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'last_user_interaction_at' => 'datetime',
            'replied_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FacebookPage, $this>
     */
    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    /**
     * @return BelongsTo<AutoReplyRule, $this>
     */
    public function autoReplyRule(): BelongsTo
    {
        return $this->belongsTo(AutoReplyRule::class);
    }

    /**
     * Determine if this conversation is within Meta's 24-hour standard messaging window.
     */
    public function isWithin24HourWindow(): bool
    {
        if ($this->channel !== 'messenger') {
            return true;
        }

        $reference = $this->last_user_interaction_at ?? $this->created_at;

        if ($reference === null) {
            return false;
        }

        $windowHours = (int) config('facebook.messaging.standard_window_hours', 24);

        return $reference->greaterThanOrEqualTo(now()->subHours($windowHours));
    }
}
