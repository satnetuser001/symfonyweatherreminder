<?php

namespace App\MessageHandler;

use App\Message\DetectTriggersMessage;
use App\Message\UpdateCityWeatherMessage;
use App\Service\Weather\WeatherService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class UpdateCityWeatherHandler
{
    public function __construct(
        private WeatherService      $weatherService,
        private MessageBusInterface $bus,
        #[Target('messengerLogger')]
        private LoggerInterface        $logger
    ) {
    }

    public function __invoke(UpdateCityWeatherMessage $message): void
    {
        $this->logger->debug('Updating weather for: {city}', ['city' => $message->city]);
        // 1. Force update from API
        $cacheEntry = $this->weatherService->refreshWeather($message->city);

        // 2. Proceed to trigger detection for this specific city cache entry
        $this->bus->dispatch(new DetectTriggersMessage($cacheEntry->getId()));
    }
}
