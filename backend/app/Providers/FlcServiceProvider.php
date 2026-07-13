<?php

namespace App\Providers;

use Flc\Dictionary\Application\Command\CurateDictionaryEntry;
use Flc\Dictionary\Application\Command\DeleteDictionaryEntry;
use Flc\Dictionary\Application\Command\UpsertDictionaryOnSave;
use Flc\Dictionary\Application\FreeDictionaryGateway;
use Flc\Dictionary\Application\Handler\CurateDictionaryEntryHandler;
use Flc\Dictionary\Application\Handler\DeleteDictionaryEntryHandler;
use Flc\Dictionary\Application\Handler\LookupWordHandler;
use Flc\Dictionary\Application\Handler\UpsertDictionaryOnSaveHandler;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Domain\Event\DictionaryContentMerged;
use Flc\Dictionary\Domain\Event\DictionaryContentReplaced;
use Flc\Dictionary\Domain\Event\DictionaryEntryCreated;
use Flc\Dictionary\Domain\Event\DictionaryEntryDeleted;
use Flc\Dictionary\Domain\Event\DictionarySaveCounted;
use Flc\Dictionary\Infrastructure\Http\HttpFreeDictionaryGateway;
use Flc\Dictionary\Infrastructure\Projection\DictionaryEntryProjector;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\EventPublisher;
use Flc\Shared\Application\EventStore;
use Flc\Shared\Application\QueryBus;
use Flc\Shared\Infrastructure\EventStore\EloquentEventStore;
use Flc\Shared\Infrastructure\Projection\SyncEventPublisher;
use Illuminate\Support\ServiceProvider;

class FlcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventStore::class, function () {
            return new EloquentEventStore([
                DictionaryEntryCreated::eventType() => DictionaryEntryCreated::class,
                DictionarySaveCounted::eventType() => DictionarySaveCounted::class,
                DictionaryContentReplaced::eventType() => DictionaryContentReplaced::class,
                DictionaryContentMerged::eventType() => DictionaryContentMerged::class,
                DictionaryEntryDeleted::eventType() => DictionaryEntryDeleted::class,
            ]);
        });

        $this->app->singleton(EventPublisher::class, function ($app) {
            return new SyncEventPublisher([
                $app->make(DictionaryEntryProjector::class),
            ]);
        });

        $this->app->singleton(AggregateRepository::class);

        $this->app->bind(FreeDictionaryGateway::class, HttpFreeDictionaryGateway::class);

        $this->app->singleton(CommandBus::class, function ($app) {
            return new CommandBus([
                UpsertDictionaryOnSave::class => UpsertDictionaryOnSaveHandler::class,
                CurateDictionaryEntry::class => CurateDictionaryEntryHandler::class,
                DeleteDictionaryEntry::class => DeleteDictionaryEntryHandler::class,
            ], $app);
        });

        $this->app->singleton(QueryBus::class, function ($app) {
            return new QueryBus([
                LookupWord::class => LookupWordHandler::class,
            ], $app);
        });
    }
}
