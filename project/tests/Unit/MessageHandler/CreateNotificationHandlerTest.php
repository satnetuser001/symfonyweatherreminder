<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Notification;
use App\Entity\Subscription;
use App\Entity\WeatherCache;
use App\Message\CreateNotificationMessage;
use App\Message\SendEmailNotificationMessage;
use App\MessageHandler\CreateNotificationHandler;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateNotificationHandlerTest extends TestCase
{
    private SubscriptionRepository $subRepoMock;
    private EntityManagerInterface $emMock;
    private MessageBusInterface $busMock;
    private LoggerInterface $loggerMock;
    private CreateNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->subRepoMock = $this->createMock(SubscriptionRepository::class);
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->busMock = $this->createMock(MessageBusInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->handler = new CreateNotificationHandler(
            $this->subRepoMock,
            $this->emMock,
            $this->busMock,
            $this->loggerMock
        );
    }

    public function testInvokeCreatesNotificationAndDispatchesEmailMessage(): void
    {
        $subId = 1;
        $message = new CreateNotificationMessage($subId, 'lower', 5.5);

        $location = $this->createMock(WeatherCache::class);
        $location->method('getCity')->willReturn('London');

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getLocation')->willReturn($location);

        $this->subRepoMock->method('find')->with($subId)->willReturn($subscription);

        $this->emMock->method('persist')->willReturnCallback(function ($notification) {
            if ($notification instanceof Notification) {
                $reflection = new \ReflectionProperty(Notification::class, 'id');
                $reflection->setValue($notification, 123);
            }
        });

        $this->emMock->expects($this->once())->method('flush');

        $this->busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof SendEmailNotificationMessage && $message->notificationId === 123;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($message);
    }

    public function testInvokeDoesNothingIfSubscriptionNotFound(): void
    {
        $this->subRepoMock->method('find')->willReturn(null);

        $this->emMock->expects($this->never())->method('persist');
        $this->busMock->expects($this->never())->method('dispatch');

        ($this->handler)(new CreateNotificationMessage(999, 'upper', 30.0));
    }
}
