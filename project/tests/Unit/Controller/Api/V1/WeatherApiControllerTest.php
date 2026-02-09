<?php

namespace App\Tests\Unit\Controller\Api\V1;

use App\Controller\Api\V1\WeatherApiController;
use App\DTO\WeatherData;
use App\Exception\WeatherApiException;
use App\Service\Weather\WeatherService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WeatherApiControllerTest extends TestCase
{
    private WeatherService $weatherServiceMock;
    private WeatherApiController $controller;

    protected function setUp(): void
    {
        $this->weatherServiceMock = $this->createMock(WeatherService::class);
        $this->controller = new WeatherApiController($this->weatherServiceMock);

        $container = $this->createMock(ContainerInterface::class);
        $this->controller->setContainer($container);
    }

    public function testGetWeatherReturnsSuccessfulResponse(): void
    {
        $city = 'Kyiv';
        $weatherData = new WeatherData(
            city: 'Kyiv',
            country: 'Ukraine',
            latitude: 50.45,
            longitude: 30.52,
            temperature: 15.5,
            conditionText: 'Partly cloudy',
            humidity: 60,
            windSpeed: 12.3,
            updatedAt: new \DateTimeImmutable('2026-01-27T10:00:00Z')
        );

        $this->weatherServiceMock->method('getWeather')->with($city)->willReturn($weatherData);

        $response = $this->controller->getWeather($city);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Kyiv', $data['city']);
        $this->assertSame('Ukraine', $data['country']);
        $this->assertEquals(15.5, $data['temperature']);
        $this->assertSame('Partly cloudy', $data['conditionText']);
        $this->assertEquals(60, $data['humidity']);
        $this->assertEquals(12.3, $data['windSpeed']);
    }

    public function testGetWeatherReturnsErrorOnWeatherApiException(): void
    {
        $city = 'UnknownCity';
        $errorMessage = 'City not found or external API error.';

        $this->weatherServiceMock->method('getWeather')
            ->with($city)
            ->willThrowException(new WeatherApiException($errorMessage));

        $response = $this->controller->getWeather($city);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame($errorMessage, $data['error']);
    }

    public function testGetWeatherReturnsInternalServerErrorOnThrowable(): void
    {
        $city = 'SomeCity';

        $this->weatherServiceMock->method('getWeather')
            ->with($city)
            ->willThrowException(new \Exception('Unexpected error'));

        $response = $this->controller->getWeather($city);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Internal server error.', $data['error']);
    }
}
