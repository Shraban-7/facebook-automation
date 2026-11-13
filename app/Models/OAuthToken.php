<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Encrypted OAuth token vault isolated from SocialAccount serialization.
 *
 * @property int $id
 * @property int $social_account_id
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string $token_type
 * @property array<int, string>|null $scopes
 * @property Carbon|null $expires_at
 * @property Carbon|null $refresh_expires_at
 * @property int $key_version
 * @property Carbon|null $rotated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'social_account_id',
    'access_token',
    'refresh_token',
    'token_type',
    'scopes',
    'expires_at',
    'refresh_expires_at',
    'key_version',
    'rotated_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class OAuthToken extends Model
{
    protected $table = 'oauth_tokens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'key_version' => 'integer',
            'rotated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
