<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Security\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class UserFixtures extends Fixture
{
    private const USERS = [
        [
            'username' => 'sophie.martin',
            'email'    => 'sophie.martin@collectivite.fr',
            'nom'      => 'Martin',
            'prenom'   => 'Sophie',
            'balance'  => 208.00,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'jean.dupont',
            'email'    => 'jean.dupont@collectivite.fr',
            'nom'      => 'Dupont',
            'prenom'   => 'Jean',
            'balance'  => 175.50,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'marie.bernard',
            'email'    => 'marie.bernard@collectivite.fr',
            'nom'      => 'Bernard',
            'prenom'   => 'Marie',
            'balance'  => 240.00,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'pierre.petit',
            'email'    => 'pierre.petit@collectivite.fr',
            'nom'      => 'Petit',
            'prenom'   => 'Pierre',
            'balance'  => 120.75,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'isabelle.robert',
            'email'    => 'isabelle.robert@collectivite.fr',
            'nom'      => 'Robert',
            'prenom'   => 'Isabelle',
            'balance'  => 195.25,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'francois.leroy',
            'email'    => 'francois.leroy@collectivite.fr',
            'nom'      => 'Leroy',
            'prenom'   => 'François',
            'balance'  => 208.00,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'christine.moreau',
            'email'    => 'christine.moreau@collectivite.fr',
            'nom'      => 'Moreau',
            'prenom'   => 'Christine',
            'balance'  => 56.50,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'nicolas.simon',
            'email'    => 'nicolas.simon@collectivite.fr',
            'nom'      => 'Simon',
            'prenom'   => 'Nicolas',
            'balance'  => 312.00,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'anne.laurent',
            'email'    => 'anne.laurent@collectivite.fr',
            'nom'      => 'Laurent',
            'prenom'   => 'Anne',
            'balance'  => 0.00,
            'roles'    => ['ROLE_AGENT'],
        ],
        [
            'username' => 'thomas.girard',
            'email'    => 'thomas.girard@collectivite.fr',
            'nom'      => 'Girard',
            'prenom'   => 'Thomas',
            'balance'  => 208.00,
            'roles'    => ['ROLE_RH'],
        ],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $index => $data) {
            $user = new User(
                id:               Uuid::v4()->toRfc4122(),
                email:            $data['email'],
                roles:            $data['roles'],
                username:         $data['username'],
                nom:              $data['nom'],
                prenom:           $data['prenom'],
                leaveBalanceValue: $data['balance'],
            );
            $user->setPassword($this->hasher->hashPassword($user, 'password'));

            $manager->persist($user);
            $this->addReference('user_' . $index, $user);
        }

        $manager->flush();
    }
}
