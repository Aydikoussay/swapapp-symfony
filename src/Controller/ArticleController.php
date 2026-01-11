<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\Request as offerRequest;

#[Route('/article')]
final class ArticleController extends AbstractController
{
    #[Route(name: 'app_article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }
    #[Route('/mine', name: 'app_article_mine', methods: ['GET'])]
    public function getArticlesOfLoggedInUser(ArticleRepository $articleRepository): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to view your articles.');
        }

        $articles = $articleRepository->findBy(['user' => $user]);

        return $this->render('article/articlemine.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/new', name: 'app_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        $article = new Article();

        // Retrieve non-file data from the request
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            $article->setUser($user);

            // Handle photo uploads
            $photos = [
                'photo1' => $request->files->get('photo1'),
                'photo2' => $request->files->get('photo2'),
                'photo3' => $request->files->get('photo3'),
            ];

            foreach ($photos as $key => $photo) {
                if ($photo) {
                    $methodName = 'set' . ucfirst($key);
                    if (method_exists($article, $methodName)) {
                        $article->$methodName(file_get_contents($photo->getPathname()));
                    }
                }
            }

            // Save the article
            $article->setDate(new \DateTime());
            $article->setStatus("encours");
            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('app_article_index');
        }

        return $this->render('article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }



    #[Route('/{id}', name: 'app_article_show', methods: ['GET'])]
    public function show(Article $article): Response
    {
        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photo1 = $form->get('photo1')->getData();
            if ($photo1) {
                $article->setPhoto1(file_get_contents($photo1->getPathname()));
            }

            $photo2 = $form->get('photo2')->getData();
            if ($photo2) {
                $article->setPhoto2(file_get_contents($photo2->getPathname()));
            }

            $photo3 = $form->get('photo3')->getData();
            if ($photo3) {
                $article->setPhoto3(file_get_contents($photo3->getPathname()));
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

 #[Route('/{id}', name: 'app_article_delete', methods: ['POST'])]
public function delete(
    Request $request,
    Article $article,
    EntityManagerInterface $entityManager
): Response {
    // 🔐 Only the owner can delete their article
    if ($article->getUser() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }

    // 🛡 CSRF protection
    if (!$this->isCsrfTokenValid(
        'delete' . $article->getId(),
        $request->request->get('_token')
    )) {
        $this->addFlash('error', 'Invalid CSRF token.');
        return $this->redirectToRoute('app_article_mine');
    }

    // 🚫 Block deletion if the article is involved in any requests
    $hasRequests = $entityManager->getRepository(OfferRequest::class)->count([
        'Articleid'   => $article,   // received offers
    ]) > 0;

    $hasProposed = $entityManager->getRepository(OfferRequest::class)->count([
        'articleprop' => $article,   // proposed offers
    ]) > 0;

    if ($hasRequests || $hasProposed) {
        $this->addFlash(
            'error',
            'You cannot delete this article because it is involved in one or more offers.'
        );
        return $this->redirectToRoute('app_article_mine');
    }

    // ✅ Safe to delete
    $entityManager->remove($article);
    $entityManager->flush();

    $this->addFlash('success', 'Article deleted successfully.');

    return $this->redirectToRoute('app_article_mine');
}

}
