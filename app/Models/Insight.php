<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $facebook_page_id
 * @property Carbon $metric_date
 * @property string $period
 * @property int $reach
 * @property int $impressions
 * @property int $engaged_users
 * @property int $engagements
 * @property int $followers
 * @property int $net_followers
 * @property int $page_views
 * @property int $reactions
 * @property int $comments_count
 * @property int $shares_count
 * @property array<string, mixed>|null $raw_metrics
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'facebook_page_id',
    'metric_date',
    'period',
    'reach',
    'impressions',
    'engaged_users',
    'engagements',
    'followers',
    'net_followers',
    'page_views',
    'reactions',
    'comments_count',
    'shares_count',
    'raw_metrics',
])]
class Insight extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'reach' => 'integer',
            'impressions' => 'integer',
            'engaged_users' => 'integer',
            'engagements' => 'integer',
            'followers' => 'integer',
            'net_followers' => 'integer',
            'page_views' => 'integer',
            'reactions' => 'integer',
            'comments_count' => 'integer',
            'shares_count' => 'integer',
            'raw_metrics' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FacebookPage, $this>
     */
    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }
}
