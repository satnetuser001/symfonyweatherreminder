<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\WeatherCache;
use App\Message\SyncWeatherCacheMessage;
use App\Message\UpdateCityWeatherMessage;
use App\MessageHandler\SyncWeatherCacheHandler;
use App\Repository\WeatherCacheRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncWeatherCacheHandlerTest extends TestCase
{
    public function testInvokeDispatchesUpdateMessagesForAllCities(): void
    {
        $city1 = $this->createMock(WeatherCache::class);
        $city1->method('getCity')->willReturn('Kyiv');

        $city2 = $this->createMock(WeatherCache::class);
        $city2->method('getCity')->willReturn('Berlin');

        $repoMock = $this->createMock(WeatherCacheRepository::class);
        $repoMock->method('findAll')->willReturn([$city1, $city2]);

        $busMock = $this->createMock(MessageBusInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        // Expect two dispatch calls, one for each city
        $busMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(UpdateCityWeatherMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $handler = new SyncWeatherCacheHandler($repoMock, $busMock, $loggerMock);
        $handler(new SyncWeatherCacheMessage());
    }
}
