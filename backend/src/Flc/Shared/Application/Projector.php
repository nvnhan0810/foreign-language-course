<?php

namespace Flc\Shared\Application;

interface Projector
{
    /**
     * @return list<class-string<\Flc\Shared\Domain\DomainEvent>>
     */
    public function subscribedEvents(): array;

    public function handle(\Flc\Shared\Domain\DomainEvent $event): void;
}
