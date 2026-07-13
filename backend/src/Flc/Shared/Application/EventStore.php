<?php

namespace Flc\Shared\Application;

interface EventStore
{
    /**
     * @param  list<\Flc\Shared\Domain\DomainEvent>  $events
     *
     * @throws \Flc\Shared\Domain\ConcurrencyException
     */
    public function append(string $aggregateType, string $aggregateId, int $expectedPlayhead, array $events): void;

    /**
     * @return list<\Flc\Shared\Domain\DomainEvent>
     */
    public function load(string $aggregateType, string $aggregateId): array;

    public function exists(string $aggregateType, string $aggregateId): bool;
}
