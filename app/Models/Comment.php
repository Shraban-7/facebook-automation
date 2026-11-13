<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $post_target_id
 * @property int $social_account_id
 * @property string $external_id
 * @property string|null $parent_external_id
 * @property string $author_external_id
 * @property string|null $author_name
 * @property string $body
 * @property bool $is_hidden
 * @property Carbon|null $replied_at
 * @property string|null $sentiment
 * @property Carbon|null $created_at_platform
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'post_target_id',
    'social_account_id',
    'external_id',
    'parent_external_id',
    'author_external_id',
    'author_name',
    'body',
    'is_hidden',
    'replied_at',
    'sentiment',
    'created_at_platform',
])]
class Comment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'replied_at' => 'datetime',
            'created_at_platform' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * @return BelongsTo<PostTarget, $this>
     */
    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }
}
