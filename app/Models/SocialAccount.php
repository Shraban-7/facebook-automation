<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property int|null $connected_by
 * @property string $provider
 * @property string $kind
 * @property string $external_id
 * @property int|null $parent_account_id
 * @property string $name
 * @property string|null $username
 * @property string|null $avatar_url
 * @property int $followers_count
 * @property string $status
 * @property array<int|string, mixed>|null $capabilities
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int|null $legacy_facebook_page_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'ulid',
    'workspace_id',
    'connected_by',
    'provider',
    'kind',
    'external_id',
    'parent_account_id',
    'name',
    'username',
    'avatar_url',
    'followers_count',
    'status',
    'capabilities',
    'settings',
    'token_expires_at',
    'last_synced_at',
    'last_error',
    'last_error_at',
    'legacy_facebook_page_id',
])]
class SocialAccount extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (SocialAccount $account): void {
            if (empty($account->ulid)) {
                $account->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'settings' => 'array',
            'followers_count' => 'integer',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'parent_account_id');
    }

    /**
     * Token vault relation — never eager-loaded by default and never serialized to frontend.
     *
     * @return HasOne<OAuthToken, $this>
     */
    public function oauthToken(): HasOne
    {
        return $this->hasOne(OAuthToken::class);
    }

    /**
     * @return HasMany<PostTarget, $this>
     */
    public function postTargets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    /**
     * @return HasMany<MetricSnapshot, $this>
     */
    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(MetricSnapshot::class);
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
}
