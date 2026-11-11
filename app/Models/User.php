<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $facebook_user_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $facebook_user_token
 * @property Carbon|null $facebook_token_expires_at
 * @property int|null $active_facebook_page_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'facebook_user_id',
    'facebook_user_token',
    'facebook_token_expires_at',
    'active_facebook_page_id',
])]
#[Hidden([
    'password',
    'facebook_user_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'facebook_user_token' => 'encrypted',
            'facebook_token_expires_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FacebookPage, $this>
     */
    public function facebookPages(): HasMany
    {
        return $this->hasMany(FacebookPage::class);
    }

    /**
     * @return BelongsTo<FacebookPage, $this>
     */
    public function activeFacebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class, 'active_facebook_page_id');
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
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Resolve the user's currently active Facebook Page or fall back to the first connected page.
     */
    public function resolveCurrentPage(): ?FacebookPage
    {
        if ($this->active_facebook_page_id !== null) {
            $activePage = $this->facebookPages()
                ->where('id', $this->active_facebook_page_id)
                ->first();

            if ($activePage !== null) {
                return $activePage;
            }
        }

        return $this->facebookPages()->orderBy('id')->first();
    }
}
