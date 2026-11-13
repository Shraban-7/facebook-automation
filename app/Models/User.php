<?php

namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'id',
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
