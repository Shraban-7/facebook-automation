<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property int $social_account_id
 * @property string $external_id
 * @property string $participant_external_id
 * @property string|null $participant_name
 * @property string $status
 * @property int|null $assigned_to
 * @property Carbon|null $last_message_at
 * @property Carbon|null $window_expires_at
 * @property int $unread_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ulid',
    'workspace_id',
    'social_account_id',
    'external_id',
    'participant_external_id',
    'participant_name',
    'status',
    'assigned_to',
    'last_message_at',
    'window_expires_at',
    'unread_count',
])]
class Conversation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation): void {
            if (empty($conversation->ulid)) {
                $conversation->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'window_expires_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<InboxMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class)->orderBy('created_at');
    }

    public function isWithinMessagingWindow(): bool
    {
        if ($this->window_expires_at === null) {
            return true;
        }

        return $this->window_expires_at->isFuture();
    }
}
