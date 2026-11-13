<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $workspace_id
 * @property int|null $author_id
 * @property string|null $title
 * @property string $body
 * @property string|null $link_url
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property string $timezone
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property array<int, string>|null $tags
 * @property bool $ai_generated
 * @property int|null $legacy_post_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ulid',
    'workspace_id',
    'author_id',
    'title',
    'body',
    'link_url',
    'status',
    'scheduled_at',
    'timezone',
    'approved_by',
    'approved_at',
    'tags',
    'ai_generated',
    'legacy_post_id',
])]
class Content extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Content $content): void {
            if (empty($content->ulid)) {
                $content->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'tags' => 'array',
            'ai_generated' => 'boolean',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<PostTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    /**
     * @return BelongsToMany<MediaAsset, $this>
     */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'content_media')
            ->withPivot(['position', 'alt_text'])
            ->orderByPivot('position');
    }
}
