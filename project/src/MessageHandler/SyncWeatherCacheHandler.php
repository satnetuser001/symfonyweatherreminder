<?php

namespace App\MessageHandler;

use App\Message\SyncWeatherCacheMessage;
use App\Message\UpdateCityWeatherMessage;
use App\Repository\WeatherCacheRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class SyncWeatherCacheHandler
{
    public function __construct(
        private WeatherCacheRepository $repository,
        private MessageBusInterface    $bus,
        #[Target('messengerLogger')]
        private LoggerInterface        $logger
    ) {
    }

    public function __invoke(SyncWeatherCacheMessage $message): void
    {
        // 1. Get all cities from cache table
        $cities = $this->repository->findAll();
        $this->logger->info('Start global sync. Found {count} cities.', ['count' => count($cities)]);

        foreach ($cities as $cacheEntry) {
            // 2. Dispatch a separate message for each city
            $this->bus->dispatch(new UpdateCityWeatherMessage(
                $cacheEntry->getCity()
            ));
        }
    }
}
