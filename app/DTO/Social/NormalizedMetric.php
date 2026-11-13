<?php

namespace App\DTO\Social;

use Carbon\CarbonInterface;

final readonly class NormalizedMetric
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $metricKey,
        public CarbonInterface $date,
        public float $value,
        public array $raw = [],
    ) {}
}
