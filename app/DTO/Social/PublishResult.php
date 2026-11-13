<?php

namespace App\DTO\Social;

final readonly class PublishResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $externalId,
        public ?string $externalUrl = null,
        public ?string $providerRequestId = null,
        public array $rawResponse = [],
    ) {}
}
