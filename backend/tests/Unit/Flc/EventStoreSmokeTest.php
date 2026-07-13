<?php

namespace Tests\Unit\Flc;

use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Dictionary\Domain\Event\DictionaryEntryCreated;
use Flc\Dictionary\Infrastructure\Projection\DictionaryEntryProjector;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\EventStore;
use Flc\Shared\Infrastructure\Projection\SyncEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventStoreSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_replay_and_project(): void
    {
        $store = $this->app->make(EventStore::class);
        $publisher = new SyncEventPublisher([
            $this->app->make(DictionaryEntryProjector::class),
        ]);
        $repo = new AggregateRepository($store, $publisher);

        $aggregate = DictionaryEntryAggregate::createFromPayload('hello', [
            'phonetic' => '/həˈloʊ/',
            'audio_url' => null,
            'source' => 'user_save',
            'meanings' => [[
                'part_of_speech' => 'noun',
                'definition' => 'A greeting',
                'examples' => ['Hello there'],
                'synonyms' => ['hi'],
                'antonyms' => [],
            ]],
            'synonyms' => ['hi'],
            'antonyms' => [],
        ]);

        $repo->save($aggregate);

        $this->assertTrue($store->exists(DictionaryEntryAggregate::aggregateType(), 'hello'));
        $this->assertDatabaseHas('dictionary_entries', ['word' => 'hello', 'save_count' => 1]);
        $this->assertDatabaseHas('dictionary_meanings', ['definition' => 'A greeting']);

        $reloaded = $repo->load(DictionaryEntryAggregate::class, 'hello');
        $this->assertInstanceOf(DictionaryEntryAggregate::class, $reloaded);
        $this->assertSame('A greeting', $reloaded->toClientPayload()['meanings'][0]['definition']);
        $this->assertContains(DictionaryEntryCreated::eventType(), array_map(
            fn ($e) => $e::eventType(),
            $store->load(DictionaryEntryAggregate::aggregateType(), 'hello')
        ));
    }
}
