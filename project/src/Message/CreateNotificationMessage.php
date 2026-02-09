<?php

namespace App\Message;

/**
 * Task to create a persistent notification record for a triggered subscription
 */
final readonly class CreateNotificationMessage
{
    public function __construct(
        public int    $subscriptionId,
        public string $triggerType, // 'lower' or 'upper'
        public float  $currentTemp
    ) {
    }
}
