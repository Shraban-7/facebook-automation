<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $facebook_page_id
 * @property string $name
 * @property string $channel
 * @property string $trigger_type
 * @property array<int, string>|null $keywords
 * @property array<int, string>|null $banned_words
 * @property string $reply_template
 * @property string|null $private_reply_template
 * @property string $moderation_action
 * @property bool $respect_24h_window
 * @property int $cooldown_seconds
 * @property int $priority
 * @property bool $is_active
 * @property int $match_count
 * @property Carbon|null $last_triggered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'facebook_page_id',
    'name',
    'channel',
    'trigger_type',
    'keywords',
    'banned_words',
    'reply_template',
    'private_reply_template',
    'moderation_action',
    'respect_24h_window',
    'cooldown_seconds',
    'priority',
    'is_active',
    'match_count',
    'last_triggered_at',
])]
class AutoReplyRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'banned_words' => 'array',
            'respect_24h_window' => 'boolean',
            'cooldown_seconds' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'match_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FacebookPage, $this>
     */
    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
