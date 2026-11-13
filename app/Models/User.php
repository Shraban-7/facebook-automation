<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'facebook_user_id',
    'facebook_user_token',
    'facebook_token_expires_at',
    'active_facebook_page_id',
    'active_workspace_id',
    'timezone',
    'locale',
    'last_login_at',
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
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'accepted_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function activeWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'active_workspace_id');
    }

    /**
     * Resolve the user's active workspace or provision a personal workspace automatically.
     */
    public function resolveCurrentWorkspace(): Workspace
    {
        if ($this->active_workspace_id !== null) {
            $active = $this->workspaces()
                ->where('workspaces.id', $this->active_workspace_id)
                ->first();

            if ($active !== null) {
                return $active;
            }
        }

        $first = $this->workspaces()->orderBy('workspaces.id')->first();
        if ($first !== null) {
            $this->update(['active_workspace_id' => $first->id]);

            return $first;
        }

        $workspace = Workspace::create([
            'name' => $this->name."'s Workspace",
            'slug' => Str::slug($this->name).'-'.$this->id.'-'.Str::lower(Str::random(4)),
            'timezone' => $this->timezone ?: 'UTC',
            'plan' => 'pro',
            'owner_id' => $this->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $this->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $this->update(['active_workspace_id' => $workspace->id]);

        return $workspace;
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
