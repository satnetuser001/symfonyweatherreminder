<?php

namespace App\Service\Weather\Provider;

use App\DTO\WeatherData;
use App\Service\Weather\WeatherProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Exception\WeatherApiException;

/**
 * Weather provider implementation for weatherapi.com
 */
class ExternalWeatherApiProvider implements WeatherProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $apiKey,
        private readonly string              $apiUrl,
        #[Target('weatherApiLogger')]
        private readonly LoggerInterface     $logger
    ) {
    }

    public function fetchWeather(string $location): WeatherData
    {
        $this->logger->info('Fetching weather data from external API for location: {location}', [
            'location' => $location
        ]);

        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/current.json', [
                'query' => [
                    'key' => $this->apiKey,
                    'q' => $location,
                    'aqi' => 'no'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            $this->logger->debug('API Response received', [
                'status' => $statusCode,
                'location' => $data['location']['name'] ?? 'unknown',
                'temp' => $data['current']['temp_c'] ?? 'n/a'
            ]);

            return new WeatherData(
                city: $data['location']['name'],
                country: $data['location']['country'],
                latitude: (float)$data['location']['lat'],
                longitude: (float)$data['location']['lon'],
                temperature: (float)$data['current']['temp_c'],
                conditionText: $data['current']['condition']['text'],
                humidity: (int)$data['current']['humidity'],
                windSpeed: (float)$data['current']['wind_kph'],
                updatedAt: new \DateTimeImmutable()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Weather API request failed: {message}', [
                'location' => $location,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new WeatherApiException('City not found or external API error.', 0, $e);
        }
    }
}
