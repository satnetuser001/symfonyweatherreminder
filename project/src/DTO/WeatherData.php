<?php

namespace App\DTO;

/**
 * Data Transfer Object for weather information
 */
readonly class WeatherData
{
    public function __construct(
        public string $city,
        public string $country,
        public float $latitude,
        public float $longitude,
        public float $temperature,
        public string $conditionText,
        public int $humidity,
        public float $windSpeed,
        public \DateTimeImmutable $updatedAt
    ) {
    }
}
