<?php

require_once 'vendor/autoload.php';

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get(EntityManagerInterface::class);
$passwordHasher = $container->get(UserPasswordHasherInterface::class);

$user = new User();
$user->setEmail('admin@example.com');
$user->setFirstname('Admin');
$user->setLastname('User');
$user->setPhonenum('123456789');
$user->setRoles(['ROLE_ADMIN']);
$hashedPassword = $passwordHasher->hashPassword($user, 'password');
$user->setPassword($hashedPassword);

$entityManager->persist($user);
$entityManager->flush();

echo "User created: admin@example.com / password\n";