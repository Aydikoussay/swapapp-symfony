<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\UserAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get('pdp')->getData();

            if ($uploadedFile) {
                // Read the uploaded image file and encode it into base64
                $fileContent = file_get_contents($uploadedFile->getPathname());
                // Save the image content in the pdp field as binary
                $user->setPdp($fileContent);
            }

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hash the password and set it for the user
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Optionally, set default roles for the user
            $user->setRoles(['ROLE_USER']);

            // Persist the new user entity into the database
            $entityManager->persist($user);
            $entityManager->flush();

            // Log the user in after successful registration
            return $security->login($user, UserAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}