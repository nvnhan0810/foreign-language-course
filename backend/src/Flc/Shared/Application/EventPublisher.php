<?php

namespace Flc\Shared\Application;

use Flc\Shared\Domain\DomainEvent;

interface EventPublisher
{
    /**
     * @param  list<DomainEvent>  $events
     */
    public function publish(array $events): void;
}
