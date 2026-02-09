<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Notification;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\WeatherCache;
use App\Message\SendEmailNotificationMessage;
use App\MessageHandler\SendEmailNotificationHandler;
use App\Repository\NotificationRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class SendEmailNotificationHandlerTest extends TestCase
{
    private NotificationRepository $notificationRepoMock;
    private MailerInterface $mailerMock;
    private LoggerInterface $loggerMock;
    private SendEmailNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->notificationRepoMock = $this->createMock(NotificationRepository::class);
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->handler = new SendEmailNotificationHandler(
            $this->notificationRepoMock,
            $this->mailerMock,
            $this->loggerMock
        );
    }

    public function testInvokeSendsEmailSuccessfully(): void
    {
        $notificationId = 1;
        $email = 'user@example.com';
        $city = 'Berlin';

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);

        $location = $this->createMock(WeatherCache::class);
        $location->method('getCity')->willReturn($city);
        $location->method('getTemperature')->willReturn(25.5);

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUser')->willReturn($user);
        $subscription->method('getLocation')->willReturn($location);

        $notification = $this->createMock(Notification::class);
        $notification->method('getId')->willReturn($notificationId);
        $notification->method('getMessage')->willReturn('Alert message');
        $notification->method('getSubscription')->willReturn($subscription);

        $this->notificationRepoMock->method('find')->with($notificationId)->willReturn($notification);

        // Expect mailer to be called with correct TemplatedEmail object
        $this->mailerMock->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $mail) use ($email, $city) {
                return $mail->getTo()[0]->getAddress() === $email &&
                    str_contains($mail->getSubject(), $city) &&
                    $mail->getHtmlTemplate() === 'emails/weather_alert.html.twig';
            }));

        ($this->handler)(new SendEmailNotificationMessage($notificationId));
    }

    public function testInvokeThrowsExceptionOnMailerError(): void
    {
        $notificationId = 1;
        $notification = $this->createMock(Notification::class);

        // Setup hierarchy with valid email
        $sub = $this->createMock(Subscription::class);
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('error@example.com');

        $loc = $this->createMock(WeatherCache::class);
        $loc->method('getCity')->willReturn('London');
        $loc->method('getTemperature')->willReturn(15.0);

        $sub->method('getUser')->willReturn($user);
        $sub->method('getLocation')->willReturn($loc);

        $notification->method('getSubscription')->willReturn($sub);
        $notification->method('getMessage')->willReturn('Test error message');

        $this->notificationRepoMock->method('find')->willReturn($notification);

        // Force mailer to throw exception
        $this->mailerMock->method('send')->willThrowException(new \Exception('SMTP Error'));

        // Expect logger to record error
        $this->loggerMock->expects($this->once())->method('error');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SMTP Error');

        ($this->handler)(new SendEmailNotificationMessage($notificationId));
    }

    public function testInvokeDoesNothingIfNotificationNotFound(): void
    {
        $notificationId = 999;

        $this->notificationRepoMock->method('find')
            ->with($notificationId)
            ->willReturn(null);

        $this->mailerMock->expects($this->never())->method('send');
        $this->loggerMock->expects($this->never())->method('error');

        ($this->handler)(new SendEmailNotificationMessage($notificationId));
    }
}
