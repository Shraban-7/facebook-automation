<?php

namespace App\DTO\Social;

use Carbon\CarbonInterface;

final readonly class DateRange
{
    public function __construct(
        public CarbonInterface $since,
        public CarbonInterface $until,
    ) {}
}
