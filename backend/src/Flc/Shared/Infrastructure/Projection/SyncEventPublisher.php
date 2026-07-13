<?php

namespace Flc\Shared\Infrastructure\Projection;

use Flc\Shared\Application\EventPublisher;
use Flc\Shared\Application\Projector;
use Flc\Shared\Domain\DomainEvent;

final class SyncEventPublisher implements EventPublisher
{
    /** @param list<Projector> $projectors */
    public function __construct(
        private readonly array $projectors,
    ) {}

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            foreach ($this->projectors as $projector) {
                if (in_array($event::class, $projector->subscribedEvents(), true)) {
                    $projector->handle($event);
                }
            }
        }
    }
}
