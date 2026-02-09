<?php

namespace App\Message;

/**
 * Task to update weather for a specific city
 */
final readonly class UpdateCityWeatherMessage
{
    public function __construct(
        public string $city
    ) {
    }
}
