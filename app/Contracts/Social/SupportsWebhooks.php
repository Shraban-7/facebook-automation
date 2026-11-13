<?php

namespace App\Contracts\Social;

use App\DTO\Social\NormalizedEvent;
use Illuminate\Http\Request;

interface SupportsWebhooks
{
    public function verify(Request $request): bool;

    /**
     * @return iterable<int, NormalizedEvent>
     */
    public function normalize(Request $request): iterable;
}
