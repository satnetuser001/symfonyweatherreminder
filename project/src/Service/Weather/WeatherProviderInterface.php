<?php

namespace App\Service\Weather;

use App\DTO\WeatherData;

/**
 * Interface for weather data providers
 */
interface WeatherProviderInterface
{
    /**
     * Fetch weather data for a specific location
     */
    public function fetchWeather(string $location): WeatherData;
}
