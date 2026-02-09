<?php

namespace App\Tests\Unit\Service\Weather;

use App\DTO\WeatherData;
use App\Entity\WeatherCache;
use App\Repository\WeatherCacheRepository;
use App\Service\Weather\WeatherProviderInterface;
use App\Service\Weather\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class WeatherServiceTest extends TestCase
{
    private WeatherProviderInterface $providerMock;
    private WeatherCacheRepository $repositoryMock;
    private EntityManagerInterface $entityManagerMock;
    private WeatherService $service;
    private int $cacheTtl = 20;

    protected function setUp(): void
    {
        $this->providerMock = $this->createMock(WeatherProviderInterface::class);
        $this->repositoryMock = $this->createMock(WeatherCacheRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->service = new WeatherService(
            $this->providerMock,
            $this->repositoryMock,
            $this->entityManagerMock,
            $this->cacheTtl
        );
    }

    public function testGetWeatherReturnsFromCacheIfFresh(): void
    {
        $city = 'London';

        $cache = new WeatherCache();
        $cache->setCity($city)
            ->setCountry('UK')
            ->setLatitude(51.5)
            ->setLongitude(-0.1)
            ->setTemperature(10.0)
            ->setConditionText('Cloudy')
            ->setHumidity(80)
            ->setWindSpeed(5.0)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['city' => $city])
            ->willReturn($cache);

        $this->providerMock->expects($this->never())->method('fetchWeather');

        $result = $this->service->getWeather($city);

        $this->assertInstanceOf(WeatherData::class, $result);
        $this->assertEquals(10.0, $result->temperature);
        $this->assertEquals(51.5, $result->latitude);
    }

    public function testGetWeatherCallsProviderIfCacheIsExpired(): void
    {
        $city = 'London';

        // Expired cache (older than 20 minutes)
        $cache = new WeatherCache();
        $cache->setCity($city)
            ->setUpdatedAt(new \DateTimeImmutable('-30 minutes'));

        $freshData = new WeatherData(
            $city, 'UK', 51.5, -0.1, 15.0, 'Sunny', 50, 10.0, new \DateTimeImmutable()
        );

        $this->repositoryMock->method('findOneBy')->willReturn($cache);

        // Provider SHOULD be called
        $this->providerMock->expects($this->once())
            ->method('fetchWeather')
            ->with($city)
            ->willReturn($freshData);

        $this->entityManagerMock->expects($this->once())->method('persist');
        $this->entityManagerMock->expects($this->once())->method('flush');

        $result = $this->service->getWeather($city);

        $this->assertEquals(15.0, $result->temperature);
    }

    public function testGetWeatherCallsProviderIfNoCacheFound(): void
    {
        $city = 'Paris';

        $this->repositoryMock->method('findOneBy')->willReturn(null);

        $freshData = new WeatherData(
            $city, 'FR', 48.8, 2.3, 20.0, 'Clear', 40, 15.0, new \DateTimeImmutable()
        );

        $this->providerMock->expects($this->once())
            ->method('fetchWeather')
            ->willReturn($freshData);

        $this->entityManagerMock->expects($this->once())->method('persist');

        $result = $this->service->getWeather($city);

        $this->assertEquals('Paris', $result->city);
    }

    public function testRefreshWeatherAlwaysCallsProviderAndUpdateCache(): void
    {
        $city = 'Berlin';

        $freshData = new WeatherData(
            $city, 'DE', 52.5, 13.4, 22.0, 'Cloudy', 45, 12.0, new \DateTimeImmutable()
        );

        $existingCache = new WeatherCache();
        $existingCache->setCity($city);

        // Репозиторий находит существующую запись
        $this->repositoryMock->method('findOneBy')
            ->with(['city' => $city])
            ->willReturn($existingCache);

        // Провайдер ОБЯЗАН быть вызван (независимо от того, свежий кэш или нет)
        $this->providerMock->expects($this->once())
            ->method('fetchWeather')
            ->with($city)
            ->willReturn($freshData);

        // Проверяем сохранение
        $this->entityManagerMock->expects($this->once())->method('persist');
        $this->entityManagerMock->expects($this->once())->method('flush');

        $result = $this->service->refreshWeather($city);

        $this->assertInstanceOf(WeatherCache::class, $result);
        $this->assertEquals(22.0, $result->getTemperature());
    }
}
