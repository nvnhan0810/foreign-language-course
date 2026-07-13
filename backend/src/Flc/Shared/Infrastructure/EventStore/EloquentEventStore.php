<?php

namespace Flc\Shared\Infrastructure\EventStore;

use Flc\Shared\Application\EventStore;
use Flc\Shared\Domain\ConcurrencyException;
use Flc\Shared\Domain\DomainEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class EloquentEventStore implements EventStore
{
    /** @param array<string, class-string<DomainEvent>> $eventTypeMap */
    public function __construct(
        private readonly array $eventTypeMap,
    ) {}

    public function append(string $aggregateType, string $aggregateId, int $expectedPlayhead, array $events): void
    {
        if ($events === []) {
            return;
        }

        DB::transaction(function () use ($aggregateType, $aggregateId, $expectedPlayhead, $events) {
            $exists = DB::table('event_store')
                ->where('aggregate_type', $aggregateType)
                ->where('aggregate_id', $aggregateId)
                ->exists();

            $actual = $exists
                ? (int) DB::table('event_store')
                    ->where('aggregate_type', $aggregateType)
                    ->where('aggregate_id', $aggregateId)
                    ->max('playhead')
                : -1;

            if ($actual !== $expectedPlayhead) {
                throw new ConcurrencyException(sprintf(
                    'Concurrency conflict on %s:%s expected playhead %d got %d',
                    $aggregateType,
                    $aggregateId,
                    $expectedPlayhead,
                    $actual
                ));
            }

            $playhead = $expectedPlayhead;
            $rows = [];

            foreach ($events as $event) {
                $playhead++;
                $rows[] = [
                    'aggregate_type' => $aggregateType,
                    'aggregate_id' => $aggregateId,
                    'playhead' => $playhead,
                    'event_type' => $event::eventType(),
                    'payload' => json_encode($event->toPayload(), JSON_THROW_ON_ERROR),
                    'metadata' => json_encode($event->metadata, JSON_THROW_ON_ERROR),
                    'recorded_at' => now(),
                ];
            }

            try {
                DB::table('event_store')->insert($rows);
            } catch (Throwable $e) {
                throw new ConcurrencyException($e->getMessage(), previous: $e);
            }
        });
    }

    public function load(string $aggregateType, string $aggregateId): array
    {
        $rows = DB::table('event_store')
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->orderBy('playhead')
            ->get();

        $events = [];

        foreach ($rows as $row) {
            $class = $this->eventTypeMap[$row->event_type] ?? null;
            if ($class === null) {
                throw new InvalidArgumentException('Unknown event type: '.$row->event_type);
            }

            $payload = is_string($row->payload)
                ? json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR)
                : (array) $row->payload;
            $metadata = $row->metadata
                ? (is_string($row->metadata)
                    ? json_decode($row->metadata, true, 512, JSON_THROW_ON_ERROR)
                    : (array) $row->metadata)
                : [];

            $events[] = new $class($aggregateId, $payload, is_array($metadata) ? $metadata : []);
        }

        return $events;
    }

    public function exists(string $aggregateType, string $aggregateId): bool
    {
        return DB::table('event_store')
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->exists();
    }
}
