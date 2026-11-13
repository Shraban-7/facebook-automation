<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_target_id
 * @property int $attempt_no
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $outcome
 * @property int|null $http_status
 * @property string|null $error_class
 * @property string|null $provider_request_id
 * @property string|null $response_excerpt
 */
#[Fillable([
    'post_target_id',
    'attempt_no',
    'started_at',
    'finished_at',
    'outcome',
    'http_status',
    'error_class',
    'provider_request_id',
    'response_excerpt',
])]
class PublishAttempt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'http_status' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PostTarget, $this>
     */
    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }
}
