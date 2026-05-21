<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'username' => 'Staff2',
                'password' => 'staff1234',
                'name' => 'Staff Two',
                'roles' => ['ROLE_STAFF'],
            ],
            [
                'username' => 'Albert2',
                'password' => 'albert1234',
                'name' => 'Albert Two',
                'roles' => ['ROLE_STAFF'],
            ],
        ];

        foreach ($users as $data) {
            $user = $manager->getRepository(User::class)->findOneBy([
                'username' => $data['username'],
            ]) ?? new User();

            $user->setUsername($data['username']);
            $user->setName($data['name']);
            $user->setRoles($data['roles']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setIsVerified(true);
            $user->setIsActive(true);
            $user->setAuthProvider('local');
            $manager->persist($user);
        }

        $manager->flush();
    }
}
