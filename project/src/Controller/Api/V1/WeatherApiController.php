<?php

namespace App\Controller\Api\V1;

use App\Exception\WeatherApiException;
use App\Service\Weather\WeatherService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for retrieving weather data via API v1
 */
#[Route('/api/v1/weather', name: 'api_v1_weather_')]
class WeatherApiController extends AbstractController
{
    public function __construct(
        private readonly WeatherService $weatherService
    ) {
    }

    /**
     * Get weather by city name
     */
    #[Route('/{city}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/weather/{city}',
        summary: 'Get current weather data for a specific city',
        security: [['Bearer' => []]],
        tags: ['Weather'], # This endpoint requires a Bearer token
        parameters: [
            new OA\Parameter(
                name: 'city',
                description: 'The name of the city to get weather for',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'Kyiv')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response with current weather data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'city', type: 'string', example: 'Kyiv'),
                        new OA\Property(property: 'temperature', type: 'number', format: 'float', example: 15.5),
                        new OA\Property(property: 'condition', type: 'string', example: 'Partly cloudy'),
                        new OA\Property(property: 'humidity', type: 'number', format: 'float', example: 60.2),
                        new OA\Property(property: 'windSpeed', type: 'number', format: 'float', example: 12.3),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2026-01-27T10:00:00Z'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid city name or weather API error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'City not found or external API error.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized (JWT token missing or invalid)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid JWT Token'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function getWeather(string $city): JsonResponse
    {
        try {
            $weather = $this->weatherService->getWeather($city);

            return $this->json($weather);
        } catch (WeatherApiException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Internal server error.'], 500);
        }
    }
}
