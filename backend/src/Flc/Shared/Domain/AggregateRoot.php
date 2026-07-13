<?php

namespace Flc\Shared\Domain;

use DomainException;
use LogicException;

abstract class AggregateRoot
{
    private string $aggregateId;

    private int $playhead = -1;

    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function __construct(string $aggregateId)
    {
        $this->aggregateId = $aggregateId;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function playhead(): int
    {
        return $this->playhead;
    }

    abstract public static function aggregateType(): string;

    /**
     * @param  list<DomainEvent>  $events
     */
    public static function reconstitute(string $aggregateId, array $events): static
    {
        $aggregate = new static($aggregateId);

        foreach ($events as $event) {
            $aggregate->apply($event);
            $aggregate->playhead++;
        }

        return $aggregate;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        if ($event->aggregateId !== $this->aggregateId) {
            throw new LogicException('Event aggregate id mismatch.');
        }

        $this->apply($event);
        $this->recordedEvents[] = $event;
        $this->playhead++;
    }

    protected function apply(DomainEvent $event): void
    {
        $method = 'apply'.$this->classBasename($event);

        if (! method_exists($this, $method)) {
            throw new DomainException(sprintf(
                'Missing apply method %s on %s for event %s',
                $method,
                static::class,
                $event::class
            ));
        }

        $this->{$method}($event);
    }

    private function classBasename(object $object): string
    {
        $parts = explode('\\', $object::class);

        return end($parts);
    }
}
