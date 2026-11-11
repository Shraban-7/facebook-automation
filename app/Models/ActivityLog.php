<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $facebook_page_id
 * @property string $category
 * @property string $action
 * @property string $level
 * @property string|null $method
 * @property string|null $endpoint
 * @property int|null $http_status
 * @property int|null $graph_error_code
 * @property int|null $graph_error_subcode
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $rate_limit_usage
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'facebook_page_id',
    'category',
    'action',
    'level',
    'method',
    'endpoint',
    'http_status',
    'graph_error_code',
    'graph_error_subcode',
    'duration_ms',
    'rate_limit_usage',
    'message',
    'context',
    'ip_address',
])]
class ActivityLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'graph_error_code' => 'integer',
            'graph_error_subcode' => 'integer',
            'duration_ms' => 'integer',
            'rate_limit_usage' => 'array',
            'context' => 'array',
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
}
