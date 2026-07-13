<?php

namespace Flc\Shared\Domain;

final class AggregateNotFoundException extends DomainException
{
    public static function for(string $aggregateType, string $aggregateId): self
    {
        return new self(sprintf('Aggregate %s:%s not found.', $aggregateType, $aggregateId));
    }
}
