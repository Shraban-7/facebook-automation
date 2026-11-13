<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([
    'id',
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
