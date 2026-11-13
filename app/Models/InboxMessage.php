<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $external_id
 * @property string $direction
 * @property string|null $body
 * @property array<int, mixed>|null $attachments
 * @property int|null $sent_by_user_id
 * @property bool $is_auto_reply
 * @property Carbon|null $delivered_at
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'conversation_id',
    'external_id',
    'direction',
    'body',
    'attachments',
    'sent_by_user_id',
    'is_auto_reply',
    'delivered_at',
    'status',
])]
class InboxMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_auto_reply' => 'boolean',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
