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
 * @property int $content_id
 * @property int $social_account_id
 * @property string $status
 * @property string|null $body_override
 * @property array<string, mixed>|null $options
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $published_at
 * @property string|null $external_id
 * @property string|null $external_url
 * @property string $idempotency_key
 * @property int $attempts
 * @property string|null $error_class
 * @property string|null $error_message
 * @property int $version
 * @property int|null $legacy_scheduled_post_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ulid',
    'content_id',
    'social_account_id',
    'status',
    'body_override',
    'options',
    'scheduled_at',
    'next_retry_at',
    'published_at',
    'external_id',
    'external_url',
    'idempotency_key',
    'attempts',
    'error_class',
    'error_message',
    'version',
    'legacy_scheduled_post_id',
])]
class PostTarget extends Model
{
    protected static function booted(): void
    {
        static::creating(function (PostTarget $target): void {
            if (empty($target->ulid)) {
                $target->ulid = (string) Str::ulid();
            }
            if (empty($target->idempotency_key)) {
                $target->idempotency_key = 'pt_'.Str::ulid()->toBase32();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'scheduled_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * @return HasMany<PublishAttempt, $this>
     */
    public function publishAttempts(): HasMany
    {
        return $this->hasMany(PublishAttempt::class)->orderByDesc('attempt_no');
    }

    public function effectiveBody(): string
    {
        return $this->body_override ?? $this->content->body;
    }
}
