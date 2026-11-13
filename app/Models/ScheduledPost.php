<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([
    'id',
])]
class ScheduledPost extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'last_attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'error_code' => 'integer',
            'failure_log' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<FacebookPage, $this>
     */
    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function due(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', 'pending')
                ->where('scheduled_for', '<=', now());
        })->orWhere(function (Builder $q): void {
            $q->where('status', 'pending')
                ->whereNotNull('next_retry_at')
                ->where('next_retry_at', '<=', now());
        });
    }

    /**
     * Append a structured failure entry to the failure_log JSON column.
     *
     * @param  array<string, mixed>  $entry
     */
    public function appendFailureLog(array $entry): void
    {
        $logs = $this->failure_log ?? [];
        $logs[] = [
            'attempt' => $this->attempts,
            'timestamp' => now()->toIso8601String(),
            ...$entry,
        ];

        $this->failure_log = $logs;
    }
}
