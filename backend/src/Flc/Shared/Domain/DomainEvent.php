<?php

namespace Flc\Shared\Domain;

abstract class DomainEvent
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly array $payload = [],
        public readonly array $metadata = [],
    ) {}

    abstract public static function eventType(): string;

    public function toPayload(): array
    {
        return $this->payload;
    }
}
