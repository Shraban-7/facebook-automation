<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property int|null $social_account_id
 * @property string $endpoint
 * @property string $method
 * @property int|null $status
 * @property int|null $duration_ms
 * @property string|null $request_id
 * @property array<string, mixed>|null $rate_usage
 * @property Carbon $created_at
 */
#[Fillable([
    'provider',
    'social_account_id',
    'endpoint',
    'method',
    'status',
    'duration_ms',
    'request_id',
    'rate_usage',
    'created_at',
])]
class ApiCallLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'duration_ms' => 'integer',
            'rate_usage' => 'array',
            'created_at' => 'datetime',
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
