<?php

namespace App\Tests\Unit\Controller\Api\V1;

use App\Controller\Api\V1\SubscriptionController;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\WeatherCache;
use App\Repository\SubscriptionRepository;
use App\Service\Weather\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SubscriptionControllerTest extends TestCase
{
    private WeatherService $weatherServiceMock;
    private EntityManagerInterface $entityManagerMock;
    private SubscriptionRepository $subscriptionRepositoryMock;
    private SubscriptionController $controller;

    protected function setUp(): void
    {
        $this->weatherServiceMock = $this->createMock(WeatherService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->subscriptionRepositoryMock = $this->createMock(SubscriptionRepository::class);

        $this->controller = new SubscriptionController(
            $this->weatherServiceMock,
            $this->entityManagerMock,
            $this->subscriptionRepositoryMock
        );

        $container = $this->createMock(ContainerInterface::class);
        $this->controller->setContainer($container);
    }

    public function testIndexReturnsUserSubscriptions(): void
    {
        $user = $this->createMock(User::class);
        $location = $this->createMock(WeatherCache::class);
        $location->method('getCity')->willReturn('Kyiv');
        $location->method('getCountry')->willReturn('UA');

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getLocation')->willReturn($location);
        $subscription->method('getTempLowerBoundary')->willReturn(10.0);
        $subscription->method('getTempUpperBoundary')->willReturn(30.0);
        $subscription->method('isActive')->willReturn(true);
        $subscription->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2023-10-27T10:00:00+00:00'));

        $this->subscriptionRepositoryMock->method('findBy')
            ->with(['user' => $user])
            ->willReturn([$subscription]);

        $response = $this->controller->index($user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data);
        $this->assertEquals('Kyiv', $data[0]['city']);
    }

    public function testStoreReturnsBadRequestIfCityMissing(): void
    {
        $user = $this->createMock(User::class);
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->store($request, $user);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('City is required', $data['error']);
    }

    public function testStoreReturnsConflictIfAlreadySubscribed(): void
    {
        $user = $this->createMock(User::class);
        $city = 'Kyiv';
        $request = new Request([], [], [], [], [], [], json_encode(['city' => $city]));

        $weatherCache = $this->createMock(WeatherCache::class);

        $weatherCacheRepoMock = $this->createMock(EntityRepository::class);
        $weatherCacheRepoMock->method('findOneBy')->with(['city' => $city])->willReturn($weatherCache);

        $this->entityManagerMock->method('getRepository')->with(WeatherCache::class)->willReturn($weatherCacheRepoMock);

        $this->subscriptionRepositoryMock->method('findOneBy')
            ->with(['user' => $user, 'location' => $weatherCache])
            ->willReturn(new Subscription());

        $response = $this->controller->store($request, $user);

        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('You are already subscribed to this city', $data['error']);

        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('You are already subscribed to this city', $data['error']);
    }

    public function testDeleteReturnsNotFoundIfSubscriptionDoesNotExist(): void
    {
        $user = $this->createMock(User::class);
        $id = 999;

        $this->subscriptionRepositoryMock->method('findOneBy')
            ->with(['id' => $id, 'user' => $user])
            ->willReturn(null);

        $response = $this->controller->delete($id, $user);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Subscription not found', $data['error']);
    }

    public function testDeleteRemovesSubscriptionSuccessfully(): void
    {
        $user = $this->createMock(User::class);
        $id = 1;
        $subscription = new Subscription();

        $this->subscriptionRepositoryMock->method('findOneBy')
            ->with(['id' => $id, 'user' => $user])
            ->willReturn($subscription);

        $this->entityManagerMock->expects($this->once())->method('remove')->with($subscription);
        $this->entityManagerMock->expects($this->once())->method('flush');

        $response = $this->controller->delete($id, $user);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testStoreReturnsBadRequestIfWeatherServiceFails(): void
    {
        $user = $this->createMock(User::class);
        $city = 'NonExistentCity';
        $request = new Request([], [], [], [], [], [], json_encode(['city' => $city]));

        // Force weather service to throw exception
        $this->weatherServiceMock->method('getWeather')
            ->willThrowException(new \Exception('City not found'));

        $response = $this->controller->store($request, $user);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('City not found or service unavailable', $data['error']);
    }

    public function testStoreCreatesSubscriptionSuccessfully(): void
    {
        $user = $this->createMock(User::class);
        $city = 'Kyiv';
        $request = new Request([], [], [], [], [], [], json_encode([
            'city' => $city,
            'tempLowerBoundary' => 5.0,
            'tempUpperBoundary' => 25.0
        ]));

        $weatherCache = $this->createMock(WeatherCache::class);
        $weatherCacheRepoMock = $this->createMock(EntityRepository::class);
        $weatherCacheRepoMock->method('findOneBy')->with(['city' => $city])->willReturn($weatherCache);

        $this->entityManagerMock->method('getRepository')->with(WeatherCache::class)->willReturn($weatherCacheRepoMock);

        // Weather service is called once
        $this->weatherServiceMock->expects($this->once())->method('getWeather')->with($city);

        // No existing subscription
        $this->subscriptionRepositoryMock->method('findOneBy')->willReturn(null);

        // Expect persist and flush
        $this->entityManagerMock->expects($this->once())->method('persist')->with($this->isInstanceOf(Subscription::class));
        $this->entityManagerMock->expects($this->once())->method('flush');

        $response = $this->controller->store($request, $user);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Subscribed successfully', $data['message']);
        $this->assertArrayHasKey('id', $data);
    }
}
