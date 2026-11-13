<?php

namespace App\DTO\Social;

use Carbon\CarbonInterface;

final readonly class NormalizedEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $type, // message.received, comment.created, account.deauthorized, etc.
        public string $accountExternalId,
        public ?string $actorExternalId = null,
        public ?string $actorName = null,
        public ?string $text = null,
        public ?string $parentExternalId = null,
        public ?CarbonInterface $occurredAt = null,
        public array $payload = [],
    ) {}
}
