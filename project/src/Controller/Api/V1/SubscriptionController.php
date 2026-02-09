<?php

namespace App\Controller\Api\V1;

use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\WeatherCache;
use App\Repository\SubscriptionRepository;
use App\Service\Weather\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/subscriptions', name: 'api_v1_subscriptions_')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionRepository $subscriptionRepository
    ) {
    }

    /**
     * List current user subscriptions.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/subscriptions',
        summary: 'List all active subscriptions for the current user',
        security: [['Bearer' => []]],
        tags: ['Subscriptions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A list of user subscriptions',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'city', type: 'string', example: 'Kyiv'),
                            new OA\Property(property: 'country', type: 'string', example: 'UA'),
                            new OA\Property(property: 'tempLowerBoundary', type: 'number', format: 'float', nullable: true, example: 0.5),
                            new OA\Property(property: 'tempUpperBoundary', type: 'number', format: 'float', nullable: true, example: 30.0),
                            new OA\Property(property: 'isActive', type: 'boolean', example: true),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-10-27T10:00:00+00:00'),
                        ],
                        type: 'object'
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized (JWT token missing or invalid)'
            ),
        ]
    )]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $subscriptions = $this->subscriptionRepository->findBy(['user' => $user]);

        $data = array_map(fn(Subscription $sub) => [
            'id' => $sub->getId(),
            'city' => $sub->getLocation()->getCity(),
            'country' => $sub->getLocation()->getCountry(),
            'tempLowerBoundary' => $sub->getTempLowerBoundary(),
            'tempUpperBoundary' => $sub->getTempUpperBoundary(),
            'isActive' => $sub->isActive(),
            'createdAt' => $sub->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $subscriptions);

        return $this->json($data);
    }

    /**
     * Subscribe to a city.
     */
    #[Route('', name: 'store', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/subscriptions',
        summary: 'Subscribe to a city with optional temperature boundaries for alerts',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Subscription details',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'city', type: 'string', example: 'Amsterdam', description: 'The city name to subscribe to'),
                    new OA\Property(property: 'tempLowerBoundary', type: 'number', format: 'float', nullable: true, example: 0.5, description: 'Optional lower temperature threshold for alerts'),
                    new OA\Property(property: 'tempUpperBoundary', type: 'number', format: 'float', nullable: true, example: 30.0, description: 'Optional upper temperature threshold for alerts'),
                ],
                type: 'object',
                example: [
                    'city' => 'Amsterdam',
                    'tempLowerBoundary' => -5.0,
                    'tempUpperBoundary' => 25.0,
                ]
            )
        ),
        tags: ['Subscriptions'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Subscription created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 10),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscribed successfully'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid input (e.g., city not found, missing city)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'City is required'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized (JWT token missing or invalid)'
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict (already subscribed to this city)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'You are already subscribed to this city'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function store(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $city = $payload['city'] ?? null;
        $lower = isset($payload['tempLowerBoundary']) ? (float)$payload['tempLowerBoundary'] : null;
        $upper = isset($payload['tempUpperBoundary']) ? (float)$payload['tempUpperBoundary'] : null;

        if (!$city) {
            return $this->json(['error' => 'City is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Validates city existence and ensures it is in cache
            $this->weatherService->getWeather($city);
        } catch (\Exception $e) {
            return $this->json(['error' => 'City not found or service unavailable'], Response::HTTP_BAD_REQUEST);
        }

        $weatherCache = $this->entityManager->getRepository(WeatherCache::class)
            ->findOneBy(['city' => $city]);

        // Check for duplicate subscription
        $existing = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'location' => $weatherCache
        ]);

        if ($existing) {
            return $this->json(['error' => 'You are already subscribed to this city'], Response::HTTP_CONFLICT);
        }

        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setLocation($weatherCache);
        $subscription->setTempLowerBoundary($lower);
        $subscription->setTempUpperBoundary($upper);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $this->json([
            'id' => $subscription->getId(),
            'message' => 'Subscribed successfully'
        ], Response::HTTP_CREATED);
    }

    /**
     * Unsubscribe.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/subscriptions/{id}',
        summary: 'Unsubscribe from a city',
        security: [['Bearer' => []]],
        tags: ['Subscriptions'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the subscription to delete',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Successfully unsubscribed (No Content)'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized (JWT token missing or invalid)'
            ),
            new OA\Response(
                response: 404,
                description: 'Subscription not found for the current user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Subscription not found'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $subscription = $this->subscriptionRepository->findOneBy([
            'id' => $id,
            'user' => $user
        ]);

        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($subscription);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
