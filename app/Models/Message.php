<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([
    'id',
])]
class Message extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'last_user_interaction_at' => 'datetime',
            'replied_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FacebookPage, $this>
     */
    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    /**
     * @return BelongsTo<AutoReplyRule, $this>
     */
    public function autoReplyRule(): BelongsTo
    {
        return $this->belongsTo(AutoReplyRule::class);
    }

    /**
     * Determine if this conversation is within Meta's 24-hour standard messaging window.
     */
    public function isWithin24HourWindow(): bool
    {
        if ($this->channel !== 'messenger') {
            return true;
        }

        $reference = $this->last_user_interaction_at ?? $this->created_at;

        if ($reference === null) {
            return false;
        }

        $windowHours = (int) config('facebook.messaging.standard_window_hours', 24);

        return $reference->greaterThanOrEqualTo(now()->subHours($windowHours));
    }
}
