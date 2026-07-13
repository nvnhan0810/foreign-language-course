<?php

namespace Flc\Shared\Application;

use Flc\Shared\Domain\AggregateRoot;
use Flc\Shared\Domain\DomainEvent;

final class AggregateRepository
{
    public function __construct(
        private readonly EventStore $eventStore,
        private readonly EventPublisher $publisher,
    ) {}

    /**
     * @template T of AggregateRoot
     *
     * @param  class-string<T>  $aggregateClass
     * @return T|null
     */
    public function load(string $aggregateClass, string $aggregateId): ?AggregateRoot
    {
        $events = $this->eventStore->load($aggregateClass::aggregateType(), $aggregateId);

        if ($events === []) {
            return null;
        }

        return $aggregateClass::reconstitute($aggregateId, $events);
    }

    public function save(AggregateRoot $aggregate): void
    {
        $events = $aggregate->releaseEvents();

        if ($events === []) {
            return;
        }

        $expectedPlayhead = $aggregate->playhead() - count($events);

        $this->eventStore->append(
            $aggregate::aggregateType(),
            $aggregate->aggregateId(),
            $expectedPlayhead,
            $events
        );

        $this->publisher->publish($events);
    }
}
