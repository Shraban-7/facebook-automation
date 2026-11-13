<?php

namespace App\Contracts\Social;

use App\DTO\Social\DateRange;
use App\DTO\Social\NormalizedMetric;
use App\Models\SocialAccount;

interface SupportsAnalytics
{
    /**
     * @return iterable<int, NormalizedMetric>
     */
    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable;
}
