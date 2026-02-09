<?php

namespace App\MessageHandler;

use App\Message\SendEmailNotificationMessage;
use App\Repository\NotificationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendEmailNotificationHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private MailerInterface        $mailer,
        #[Target('messengerLogger')]
        private LoggerInterface        $logger
    ) {
    }

    public function __invoke(SendEmailNotificationMessage $message): void
    {
        $notification = $this->notificationRepository->find($message->notificationId);
        if (!$notification) {
            return;
        }

        $subscription = $notification->getSubscription();
        $user = $subscription->getUser();

        try {
            $email = (new TemplatedEmail())
                ->from('noreply@symfonyweatherreminder.mooo.com')
                ->to($user->getEmail())
                ->subject('Weather Alert: ' . $subscription->getLocation()->getCity())
                ->htmlTemplate('emails/weather_alert.html.twig')
                ->context([
                    'city' => $subscription->getLocation()->getCity(),
                    'alert_message' => $notification->getMessage(),
                    'current_temp' => $subscription->getLocation()->getTemperature(),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Email sent to {email} for notification ID {id}', [
                'email' => $user->getEmail(),
                'id' => $notification->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email to {email}: {error}', [
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger messenger retry strategy
            throw $e;
        }
    }
}
