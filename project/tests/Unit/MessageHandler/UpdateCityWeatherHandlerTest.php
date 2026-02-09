<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\WeatherCache;
use App\Message\DetectTriggersMessage;
use App\Message\UpdateCityWeatherMessage;
use App\MessageHandler\UpdateCityWeatherHandler;
use App\Service\Weather\WeatherService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class UpdateCityWeatherHandlerTest extends TestCase
{
    public function testInvokeRefreshesWeatherAndDispatchesDetectTriggers(): void
    {
        $city = 'London';
        $weatherId = 42;

        $weatherServiceMock = $this->createMock(WeatherService::class);
        $busMock = $this->createMock(MessageBusInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        $cacheEntry = $this->createMock(WeatherCache::class);
        $cacheEntry->method('getId')->willReturn($weatherId);

        // Expect service to refresh data
        $weatherServiceMock->expects($this->once())
            ->method('refreshWeather')
            ->with($city)
            ->willReturn($cacheEntry);

        // Expect next message in chain
        $busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($weatherId) {
                return $message instanceof DetectTriggersMessage && $message->weatherCacheId === $weatherId;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $handler = new UpdateCityWeatherHandler($weatherServiceMock, $busMock, $loggerMock);
        $handler(new UpdateCityWeatherMessage($city));
    }
}
