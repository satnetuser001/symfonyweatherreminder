<?php

namespace App\DataFixtures;

use App\Entity\WeatherCache;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

class WeatherCacheFixtures extends Fixture
{
    public const CITY_PREFIX = 'city-';

    public function __construct(
        #[Target('fixturesLogger')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->logger->info('Starting to load WeatherCache fixtures...');

        $cities = [
            ['city' => 'Kyiv', 'temp' => 5.0, 'lat' => 50.43, 'lon' => 30.51],
            ['city' => 'Dnipropetrovsk', 'temp' => -1.0, 'lat' => 48.45, 'lon' => 34.98],
            ['city' => 'London', 'temp' => 3.0, 'lat' => 51.51, 'lon' => -0.1],
            ['city' => 'Lviv', 'temp' => -3.7, 'lat' => 49.83, 'lon' => 24.0],
            ['city' => 'Dubai', 'temp' => 35.0, 'lat' => 25.2, 'lon' => 55.2],
            ['city' => 'Oslo', 'temp' => -10.0, 'lat' => 59.9, 'lon' => 10.7],
            ['city' => 'Paris', 'temp' => 12.0, 'lat' => 48.8, 'lon' => 2.3],
            ['city' => 'Berlin', 'temp' => 8.0, 'lat' => 52.5, 'lon' => 13.4],
            ['city' => 'Rome', 'temp' => 18.0, 'lat' => 41.9, 'lon' => 12.4],
            ['city' => 'Tokyo', 'temp' => 15.0, 'lat' => 35.6, 'lon' => 139.6],
        ];

        foreach ($cities as $c) {
            $cache = new WeatherCache();
            $cache->setCity($c['city'])
                ->setCountry('Country')
                ->setTemperature($c['temp'])
                ->setLatitude($c['lat'])
                ->setLongitude($c['lon'])
                ->setConditionText('Cloudy')
                ->setHumidity(80)
                ->setWindSpeed(10.0)
                ->setUpdatedAt(new \DateTimeImmutable());

            $manager->persist($cache);
            $this->addReference(self::CITY_PREFIX . $c['city'], $cache);

            $this->logger->debug('Cached weather for {city} ({temp}°C)', [
                'city' => $c['city'],
                'temp' => $c['temp'],
            ]);
        }

        $manager->flush();
        $this->logger->info('WeatherCache fixtures successfully flushed.');
    }
}
