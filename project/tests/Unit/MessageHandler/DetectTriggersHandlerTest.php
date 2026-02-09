<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\WeatherCache;
use App\Message\CreateNotificationMessage;
use App\Message\DetectTriggersMessage;
use App\MessageHandler\DetectTriggersHandler;
use App\Repository\SubscriptionRepository;
use App\Repository\WeatherCacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DetectTriggersHandlerTest extends TestCase
{
    private WeatherCacheRepository $weatherRepoMock;
    private SubscriptionRepository $subRepoMock;
    private MessageBusInterface $busMock;
    private EntityManagerInterface $emMock;
    private LoggerInterface $loggerMock;
    private DetectTriggersHandler $handler;

    protected function setUp(): void
    {
        $this->weatherRepoMock = $this->createMock(WeatherCacheRepository::class);
        $this->subRepoMock = $this->createMock(SubscriptionRepository::class);
        $this->busMock = $this->createMock(MessageBusInterface::class);
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->handler = new DetectTriggersHandler(
            $this->weatherRepoMock,
            $this->subRepoMock,
            $this->busMock,
            $this->emMock,
            $this->loggerMock
        );
    }

    public function testInvokeTriggersLowerBoundary(): void
    {
        $weatherId = 1;
        $temp = 5.0; // Current temperature
        $boundary = 10.0; // Threshold

        $weather = $this->createMock(WeatherCache::class);
        $weather->method('getTemperature')->willReturn($temp);
        $this->weatherRepoMock->method('find')->with($weatherId)->willReturn($weather);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@example.com');

        $sub = $this->createMock(Subscription::class);
        $sub->method('getId')->willReturn(123);
        $sub->method('getUser')->willReturn($user);
        $sub->method('getLocation')->willReturn($weather);
        $sub->method('getTempLowerBoundary')->willReturn($boundary);
        $sub->method('isLowerTriggered')->willReturn(false); // Not triggered yet

        // Expectations
        $sub->expects($this->once())->method('setIsLowerTriggered')->with(true);
        $this->busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CreateNotificationMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->subRepoMock->method('findBy')->willReturn([$sub]);

        ($this->handler)(new DetectTriggersMessage($weatherId));
    }

    public function testInvokeResetsTriggerIfBackToNormal(): void
    {
        $weatherId = 1;
        $temp = 15.0; // Normal temperature
        $boundary = 10.0; // Threshold

        $weather = $this->createMock(WeatherCache::class);
        $weather->method('getTemperature')->willReturn($temp);
        $this->weatherRepoMock->method('find')->willReturn($weather);

        $sub = $this->createMock(Subscription::class);
        $sub->method('getTempLowerBoundary')->willReturn($boundary);
        // If it was already triggered, it should reset now
        $sub->expects($this->once())->method('setIsLowerTriggered')->with(false);

        // Bus should NOT be called
        $this->busMock->expects($this->never())->method('dispatch');

        $this->subRepoMock->method('findBy')->willReturn([$sub]);

        ($this->handler)(new DetectTriggersMessage($weatherId));
    }

    public function testInvokeDoesNothingIfWeatherNotFound(): void
    {
        $weatherId = 999;
        $this->weatherRepoMock->method('find')->with($weatherId)->willReturn(null);

        $this->loggerMock->expects($this->never())->method('debug');
        $this->subRepoMock->expects($this->never())->method('findBy');

        ($this->handler)(new DetectTriggersMessage($weatherId));
    }

    public function testInvokeTriggersUpperBoundary(): void
    {
        $weatherId = 1;
        $temp = 35.0;
        $boundary = 30.0;

        $weather = $this->createMock(WeatherCache::class);
        $weather->method('getTemperature')->willReturn($temp);
        $this->weatherRepoMock->method('find')->with($weatherId)->willReturn($weather);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@example.com');

        $sub = $this->createMock(Subscription::class);
        $sub->method('getId')->willReturn(456); // Устанавливаем ID для мока
        $sub->method('getUser')->willReturn($user);
        $sub->method('getLocation')->willReturn($weather);
        $sub->method('getTempUpperBoundary')->willReturn($boundary);
        $sub->method('isUpperTriggered')->willReturn(false);

        // Ожидаем установку флага и отправку сообщения
        $sub->expects($this->once())->method('setIsUpperTriggered')->with(true);
        $this->busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof CreateNotificationMessage && $message->triggerType === 'upper';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->subRepoMock->method('findBy')->willReturn([$sub]);

        ($this->handler)(new DetectTriggersMessage($weatherId));
    }

    public function testInvokeResetsUpperTriggerIfBackToNormal(): void
    {
        $weatherId = 1;
        $temp = 25.0; // Normal
        $boundary = 30.0;

        $weather = $this->createMock(WeatherCache::class);
        $weather->method('getTemperature')->willReturn($temp);
        $this->weatherRepoMock->method('find')->willReturn($weather);

        $sub = $this->createMock(Subscription::class);
        $sub->method('getTempUpperBoundary')->willReturn($boundary);

        $sub->expects($this->once())->method('setIsUpperTriggered')->with(false);
        $this->busMock->expects($this->never())->method('dispatch');

        $this->subRepoMock->method('findBy')->willReturn([$sub]);

        ($this->handler)(new DetectTriggersMessage($weatherId));
    }
}
