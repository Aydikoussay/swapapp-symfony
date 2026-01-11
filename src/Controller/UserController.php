<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Entity\Article;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/user')]
final class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    // Inject EntityManagerInterface into the constructor
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('The user does not exist');
        }

        // Check if the user has a photo and encode it for display


        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/{id?}', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user = null, EntityManagerInterface $entityManager, Security $security): Response
    {
        if (!$user) {
            $user = $security->getUser();
            if (!$user) {
                throw $this->createAccessDeniedException('No user is logged in.');
            }
        } else {
            $user = $entityManager->getRepository(User::class)->find($user->getId());
            if (!$user) {
                throw $this->createNotFoundException('User not found');
            }
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the uploaded file from the form
            $uploadedFile = $form->get('pdp')->getData();

            if ($uploadedFile) {
                // Convert the file content to binary
                $fileContent = file_get_contents($uploadedFile->getPathname());

                // Update the user's profile photo with the new binary data
                $user->setPdp($fileContent);
            }

            // Save the changes to the database
            $entityManager->flush();

            // Redirect to the profile page or wherever you want
            return $this->redirectToRoute('app_user_edit', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
