<?php

namespace App\Service\Weather;

use App\DTO\WeatherData;
use App\Entity\WeatherCache;
use App\Repository\WeatherCacheRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Main service for weather data orchestration
 */
class WeatherService
{
    public function __construct(
        private readonly WeatherProviderInterface $provider,
        private readonly WeatherCacheRepository   $repository,
        private readonly EntityManagerInterface   $entityManager,
        private readonly int                      $cacheTtlMinutes
    ) {
    }

    /**
     * Get weather data for a location (from cache or external API)
     */
    public function getWeather(string $locationName): WeatherData
    {
        // 1. Try to find in database
        $cache = $this->repository->findOneBy(['city' => $locationName]);

        // 2. If exists and fresh, return from cache
        if ($cache && $this->isFresh($cache)) {
            return $this->mapEntityToDto($cache);
        }

        // 3. Otherwise, fetch fresh data from provider
        $data = $this->provider->fetchWeather($locationName);

        // 4. Update or create cache record
        $this->updateCache($cache, $data);

        return $data;
    }

    /**
     * Force refresh weather data from external provider and update cache
     */
    public function refreshWeather(string $locationName): WeatherCache
    {
        $data = $this->provider->fetchWeather($locationName);
        $cache = $this->repository->findOneBy(['city' => $locationName]);

        return $this->updateCache($cache, $data);
    }

    private function isFresh(WeatherCache $cache): bool
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $cache->getUpdatedAt()->getTimestamp();

        return $diff < ($this->cacheTtlMinutes * 60);
    }

    private function updateCache(?WeatherCache $cache, WeatherData $data): WeatherCache
    {
        if (!$cache) {
            $cache = new WeatherCache();
            $cache->setCity($data->city);
        }

        $cache->setCountry($data->country)
            ->setLatitude($data->latitude)
            ->setLongitude($data->longitude)
            ->setTemperature($data->temperature)
            ->setConditionText($data->conditionText)
            ->setHumidity($data->humidity)
            ->setWindSpeed($data->windSpeed)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($cache);
        $this->entityManager->flush();

        return $cache;
    }

    private function mapEntityToDto(WeatherCache $cache): WeatherData
    {
        return new WeatherData(
            $cache->getCity(),
            $cache->getCountry(),
            $cache->getLatitude(),
            $cache->getLongitude(),
            $cache->getTemperature(),
            $cache->getConditionText(),
            $cache->getHumidity(),
            $cache->getWindSpeed(),
            $cache->getUpdatedAt()
        );
    }
}
