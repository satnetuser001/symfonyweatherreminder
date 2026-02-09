<?php

namespace App\MessageHandler;

use App\Entity\Subscription;
use App\Message\CreateNotificationMessage;
use App\Message\DetectTriggersMessage;
use App\Repository\SubscriptionRepository;
use App\Repository\WeatherCacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DetectTriggersHandler
{
    public function __construct(
        private WeatherCacheRepository $weatherRepository,
        private SubscriptionRepository $subscriptionRepository,
        private MessageBusInterface    $bus,
        private EntityManagerInterface $entityManager,
        #[Target('messengerLogger')]
        private LoggerInterface        $logger
    ) {
    }

    public function __invoke(DetectTriggersMessage $message): void
    {
        $weather = $this->weatherRepository->find($message->weatherCacheId);
        if (!$weather) {
            return;
        }

        $this->logger->debug('Detecting triggers for {city} (Temp: {temp})', [
            'city' => $weather->getCity(),
            'temp' => $weather->getTemperature()
        ]);

        $subscriptions = $this->subscriptionRepository->findBy([
            'location' => $weather,
            'isActive' => true
        ]);

        foreach ($subscriptions as $sub) {
            $this->processSubscription($sub, $weather->getTemperature());
        }

        $this->entityManager->flush();
    }

    private function processSubscription(Subscription $sub, float $currentTemp): void
    {
        // Check Lower Boundary
        if ($sub->getTempLowerBoundary() !== null) {
            if ($currentTemp < $sub->getTempLowerBoundary()) {
                if (!$sub->isLowerTriggered()) {
                    $this->logger->info('LOWER trigger hit for user {user} in {city}', [
                        'user' => $sub->getUser()->getEmail(),
                        'city' => $sub->getLocation()->getCity()
                    ]);
                    $sub->setIsLowerTriggered(true);
                    $this->bus->dispatch(new CreateNotificationMessage($sub->getId(), 'lower', $currentTemp));
                }
            } else {
                $sub->setIsLowerTriggered(false); // Reset trigger if back to normal
            }
        }

        // Check Upper Boundary
        if ($sub->getTempUpperBoundary() !== null) {
            if ($currentTemp > $sub->getTempUpperBoundary()) {
                if (!$sub->isUpperTriggered()) {
                    $sub->setIsUpperTriggered(true);
                    $this->bus->dispatch(new CreateNotificationMessage($sub->getId(), 'upper', $currentTemp));
                }
            } else {
                $sub->setIsUpperTriggered(false); // Reset trigger if back to normal
            }
        }
    }
}
