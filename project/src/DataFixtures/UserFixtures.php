<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const USER_TEST = 'user-test';
    public const USER_SECOND = 'user-second';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Target('fixturesLogger')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->logger->info('Starting to load User fixtures...');

        $user1 = new User();
        $user1->setEmail('test@example.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password123'));
        $user1->setRoles(['ROLE_USER']);
        $manager->persist($user1);
        // Save the link for other fixtures
        $this->addReference(self::USER_TEST, $user1);
        $this->logger->debug('Created user: {email}', ['email' => $user1->getEmail()]);

        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password123'));
        $user2->setRoles(['ROLE_USER']);
        $manager->persist($user2);
        $this->addReference(self::USER_SECOND, $user2);
        $this->logger->debug('Created user: {email}', ['email' => $user2->getEmail()]);

        $manager->flush();
        $this->logger->info('User fixtures successfully flushed to database.');
    }
}
