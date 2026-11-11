<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $facebook_page_id
 * @property string|null $facebook_post_id
 * @property string $type
 * @property string $content
 * @property array<int, string>|null $media_urls
 * @property string|null $link_url
 * @property string|null $link_title
 * @property string $status
 * @property Carbon|null $published_at
 * @property string|null $permalink_url
 * @property array<string, int|float>|null $metrics
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id',
    'facebook_page_id',
    'facebook_post_id',
    'type',
    'content',
    'media_urls',
    'link_url',
    'link_title',
    'status',
    'published_at',
    'permalink_url',
    'metrics',
    'last_error',
])]
class Post extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_urls' => 'array',
            'metrics' => 'array',
            'published_at' => 'datetime',
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
     * @return HasOne<ScheduledPost, $this>
     */
    public function scheduledPost(): HasOne
    {
        return $this->hasOne(ScheduledPost::class)->latestOfMany();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function drafts(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}
