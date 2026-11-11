<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $page_id
 * @property string $name
 * @property string|null $category
 * @property string|null $avatar_url
 * @property int $followers_count
 * @property string $access_token
 * @property string|null $user_access_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $token_last_refreshed_at
 * @property array<int, string>|null $permissions
 * @property array<int, string>|null $tasks
 * @property bool $webhook_subscribed
 * @property string $status
 * @property int|null $last_error_code
 * @property string|null $last_error_message
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id',
    'page_id',
    'name',
    'category',
    'avatar_url',
    'followers_count',
    'access_token',
    'user_access_token',
    'token_expires_at',
    'token_last_refreshed_at',
    'permissions',
    'tasks',
    'webhook_subscribed',
    'status',
    'last_error_code',
    'last_error_message',
    'last_synced_at',
])]
#[Hidden([
    'access_token',
    'user_access_token',
])]
class FacebookPage extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'user_access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'token_last_refreshed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'permissions' => 'array',
            'tasks' => 'array',
            'webhook_subscribed' => 'boolean',
            'followers_count' => 'integer',
            'last_error_code' => 'integer',
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
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<ScheduledPost, $this>
     */
    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    /**
     * @return HasMany<AutoReplyRule, $this>
     */
    public function autoReplyRules(): HasMany
    {
        return $this->hasMany(AutoReplyRule::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<WebhookEvent, $this>
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    /**
     * @return HasMany<Insight, $this>
     */
    public function insights(): HasMany
    {
        return $this->hasMany(Insight::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isTokenExpired(): bool
    {
        if ($this->status === 'expired' || $this->status === 'revoked') {
            return true;
        }

        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function isTokenExpiringSoon(int $days = 7): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->isBefore(now()->addDays($days));
    }
}
