<?php

namespace App\DataFixtures;

use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\WeatherCache;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

class SubscriptionFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        #[Target('fixturesLogger')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->logger->info('Starting to load Subscription fixtures...');

        $user1 = $this->getReference(UserFixtures::USER_TEST, User::class);
        $user2 = $this->getReference(UserFixtures::USER_SECOND, User::class);

        $commonCities = ['Kyiv', 'London', 'Lviv'];

        foreach ($commonCities as $city) {
            $sub1 = new Subscription();
            $sub1->setUser($user1);
            $sub1->setLocation($this->getReference(WeatherCacheFixtures::CITY_PREFIX . $city, WeatherCache::class));
            $sub1->setTempLowerBoundary(0.0);
            $manager->persist($sub1);
            $this->logger->debug('Created subscription: User {email}, City {city}', [
                'email' => $user1->getEmail(),
                'city' => $city,
            ]);

            $sub2 = new Subscription();
            $sub2->setUser($user2);
            $sub2->setLocation($this->getReference(WeatherCacheFixtures::CITY_PREFIX . $city, WeatherCache::class));
            $sub2->setTempLowerBoundary(-10.0);
            $manager->persist($sub2);
            $this->logger->debug('Created subscription: User {email}, City {city}', [
                'email' => $user2->getEmail(),
                'city' => $city,
            ]);
        }

        $sub3 = new Subscription();
        $sub3->setUser($user1);
        $sub3->setLocation($this->getReference(WeatherCacheFixtures::CITY_PREFIX . 'Oslo', WeatherCache::class));
        $sub3->setIsLowerTriggered(true);
        $sub3->setTempLowerBoundary(0.0);
        $manager->persist($sub3);
        $this->logger->debug('Created subscription: User {email}, City {city}', [
            'email' => $user1->getEmail(),
            'city' => 'Oslo',
        ]);

        $sub4 = new Subscription();
        $sub4->setUser($user1);
        $sub4->setLocation($this->getReference(WeatherCacheFixtures::CITY_PREFIX . 'Dubai', WeatherCache::class));
        $sub4->setIsActive(false);
        $sub4->setTempUpperBoundary(30.0);
        $manager->persist($sub4);
        $this->logger->debug('Created subscription: User {email}, City {city}', [
            'email' => $user1->getEmail(),
            'city' => 'Dubai',
        ]);

        $sub5 = new Subscription();
        $sub5->setUser($user2);
        $sub5->setLocation($this->getReference(WeatherCacheFixtures::CITY_PREFIX . 'Tokyo', WeatherCache::class));
        $sub5->setIsUpperTriggered(true);
        $sub5->setTempUpperBoundary(10.0);
        $manager->persist($sub5);
        $this->logger->debug('Created subscription: User {email}, City {city}', [
            'email' => $user2->getEmail(),
            'city' => 'Tokyo',
        ]);

        $manager->flush();
        $this->logger->info('Subscription fixtures successfully flushed.');
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            WeatherCacheFixtures::class,
        ];
    }
}
