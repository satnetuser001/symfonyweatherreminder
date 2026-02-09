<?php

namespace App\Tests\Unit\Controller\Api\V1;

use App\Controller\Api\V1\UserApiController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserApiControllerTest extends TestCase
{
    private EntityManagerInterface $entityManagerMock;
    private UserPasswordHasherInterface $passwordHasherMock;
    private UserApiController $controller;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasherMock = $this->createMock(UserPasswordHasherInterface::class);

        $this->controller = new UserApiController($this->entityManagerMock, $this->passwordHasherMock);

        // Mock container to allow AbstractController::json() method call
        $container = $this->createMock(ContainerInterface::class);
        $this->controller->setContainer($container);
    }

    public function testStoreReturnsBadRequestIfEmailOrPasswordMissing(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Email and password are required', $response->getContent());
    }

    public function testStoreReturnsConflictIfUserExists(): void
    {
        $email = 'exists@example.com';
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));

        $userRepositoryMock = $this->createMock(EntityRepository::class);
        $userRepositoryMock->method('findOneBy')->with(['email' => $email])->willReturn(new User());

        $this->entityManagerMock->method('getRepository')->with(User::class)->willReturn($userRepositoryMock);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertStringContainsString('User already exists', $response->getContent());
    }

    public function testStoreCreatesNewUserSuccessfully(): void
    {
        $email = 'newuser@example.com';
        $password = 'password123';
        $hashedPassword = 'hashedPasswordMock';

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $userRepositoryMock = $this->createMock(EntityRepository::class);
        $userRepositoryMock->method('findOneBy')->willReturn(null);

        $this->entityManagerMock->method('getRepository')->with(User::class)->willReturn($userRepositoryMock);

        $this->passwordHasherMock->method('hashPassword')
            ->with($this->isInstanceOf(User::class), $password)
            ->willReturn($hashedPassword);

        $this->entityManagerMock->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($user) use ($email, $hashedPassword) {
                return $user instanceof User
                    && $user->getEmail() === $email
                    && $user->getPassword() === $hashedPassword;
            }));

        $this->entityManagerMock->expects($this->once())->method('flush');

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertSame($email, $data['email']);
        $this->assertSame('User registered successfully', $data['message']);
        $this->assertArrayHasKey('id', $data);
    }
}
