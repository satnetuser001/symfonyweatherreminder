<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\CreateNotificationMessage;
use App\Message\SendEmailNotificationMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final readonly class CreateNotificationHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $bus,
        #[Target('messengerLogger')]
        private LoggerInterface        $logger
    ) {
    }

    public function __invoke(CreateNotificationMessage $message): void
    {
        $subscription = $this->subscriptionRepository->find($message->subscriptionId);
        if (!$subscription) {
            return;
        }

        $notification = new Notification();
        $notification->setSubscription($subscription);

        $notification->setMessage(sprintf(
            'Weather alert for %s: current temperature %.1f°C crossed your %s boundary.',
            $subscription->getLocation()->getCity(),
            $message->currentTemp,
            $message->triggerType
        ));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->logger->info('Notification record created (ID: {id})', ['id' => $notification->getId()]);

        $this->bus->dispatch(new SendEmailNotificationMessage($notification->getId()));
    }
}
