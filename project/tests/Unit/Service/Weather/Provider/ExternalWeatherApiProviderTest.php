<?php

namespace App\Tests\Unit\Service\Weather\Provider;

use App\DTO\WeatherData;
use App\Exception\WeatherApiException;
use App\Service\Weather\Provider\ExternalWeatherApiProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ExternalWeatherApiProviderTest extends TestCase
{
    private HttpClientInterface $httpClientMock;
    private LoggerInterface $loggerMock;
    private ExternalWeatherApiProvider $provider;
    private string $apiKey = 'test_key';
    private string $apiUrl = 'http://api.test';

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->provider = new ExternalWeatherApiProvider(
            $this->httpClientMock,
            $this->apiKey,
            $this->apiUrl,
            $this->loggerMock
        );
    }

    public function testFetchWeatherReturnsWeatherDataOnSuccess(): void
    {
        $location = 'Kyiv';
        $apiResponseData = [
            'location' => [
                'name' => 'Kyiv',
                'country' => 'Ukraine',
                'lat' => 50.45,
                'lon' => 30.52
            ],
            'current' => [
                'temp_c' => 20.5,
                'condition' => ['text' => 'Sunny'],
                'humidity' => 45,
                'wind_kph' => 15.0
            ]
        ];

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('toArray')->willReturn($apiResponseData);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('GET', $this->apiUrl . '/current.json', $this->callback(function ($options) use ($location) {
                return $options['query']['key'] === $this->apiKey && $options['query']['q'] === $location;
            }))
            ->willReturn($responseMock);

        $result = $this->provider->fetchWeather($location);

        $this->assertInstanceOf(WeatherData::class, $result);
        $this->assertEquals('Kyiv', $result->city);
        $this->assertEquals(20.5, $result->temperature);
        $this->assertEquals('Sunny', $result->conditionText);
    }

    public function testFetchWeatherThrowsWeatherApiExceptionOnError(): void
    {
        $location = 'InvalidCity';

        $this->httpClientMock->method('request')
            ->willThrowException(new \Exception('API Error'));

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('City not found or external API error.');

        $this->provider->fetchWeather($location);
    }
}
