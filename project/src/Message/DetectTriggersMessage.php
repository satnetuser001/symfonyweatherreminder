<?php

namespace App\Message;

/**
 * Task to check if weather thresholds are crossed for a specific city
 */
final readonly class DetectTriggersMessage
{
    public function __construct(
        public int $weatherCacheId
    ) {
    }
}
