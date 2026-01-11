<?php

namespace App\Controller;

use App\Entity\Request as OfferRequest;
use App\Entity\Article;
use App\Form\RequestType;
use App\Repository\ArticleRepository;
use App\Repository\RequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/request')]
final class RequestController extends AbstractController
{
    private MailerInterface $mailer;
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }
    #[Route(name: 'app_request_index', methods: ['GET'])]
    public function index(RequestRepository $requestRepository): Response
    {
        $allRequests = $requestRepository->findAll();

        return $this->render('request/index.html.twig', [
            'allRequests' => $allRequests,
        ]);
    }

    #[Route('/request/made',name: 'app_request_made', methods: ['GET'])]
    public function madeRequests(RequestRepository $requestRepository): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to view your requests.');
        }

        // Requests the user has made (as requester)
        $madeRequests = $requestRepository->findBy(['userid' => $user]);

        return $this->render('request/maderequest.html.twig', [
            'madeRequests' => $madeRequests,
        ]);
    }
    #[Route('/request/received',name: 'app_request_received', methods: ['GET'])]
    public function receivedRequests(RequestRepository $requestRepository): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to view your requests.');
        }

        // Requests the user has received (as article owner)
        $receivedRequests = $requestRepository->createQueryBuilder('r')
            ->innerJoin('r.Articleid', 'a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return $this->render('request/requestesrecieved.html.twig', [
            'receivedRequests' => $receivedRequests,
        ]);
    }
//    #[Route('/new', name: 'app_request_new', methods: ['GET', 'POST'])]
//    public function new(Request $request, EntityManagerInterface $entityManager): Response
//    {
//        $offerRequest = new OfferRequest();
//        $form = $this->createForm(RequestType::class, $offerRequest);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            $entityManager->persist($offerRequest);
//            $entityManager->flush();
//
//            return $this->redirectToRoute('app_request_index', [], Response::HTTP_SEE_OTHER);
//        }
//
//        return $this->render('request/new.html.twig', [
//            'request' => $offerRequest,
//            'form' => $form,
//        ]);
//    }

    #[Route('/{id}/make-offer', name: 'app_request_make_offer', methods: ['GET', 'POST'])]
    public function makeOffer(
        Request $request,
        ArticleRepository $articleRepository,
        EntityManagerInterface $entityManager,
        int $id
    ): Response {
        $user = $this->getUser();

        #if (!$user) {
           # throw $this->createAccessDeniedException('You must be logged in to make an offer.');
        #}


        $article = $articleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException('The requested article does not exist.');
        }

        $articleOwner = $article->getUser(); // Assuming Article entity has a 'getUser()' method
        if (!$articleOwner || !$articleOwner->getEmail()) {
            $this->addFlash('error', 'The article owner does not have a valid email address.');
            return $this->redirectToRoute('app_request_index');
        }

        // Find the articles belonging to the logged-in user
        $userArticles = $articleRepository->findBy(['user' => $user]);

        if (!$userArticles) {
            $this->addFlash('warning', 'You have no articles to offer.');
            return $this->redirectToRoute('app_article_new');
        }

        // Create a new offer request
        $offerRequest = new OfferRequest();
        $form = $this->createFormBuilder($offerRequest)
            ->add('articleprop', EntityType::class, [
                'class' => Article::class,
                'choices' => $userArticles,
                'choice_label' => 'titre',
                'label' => 'Select Your Article',
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'label' => 'Message (Optional)',
                'attr' => ['rows' => 5, 'cols' => 50],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set the fields for OfferRequest
            $offerRequest->setUserid($user);
            $offerRequest->setDatedemande(new \DateTime());
            $offerRequest->setArticleid($article);
            $offerRequest->setStatus('encours'); // Default status is 'encours'
            $offerRequest->setMessage($form->get('message')->getData());

            // Increment the number of offers only after the form is submitted successfully
            $article->setNumberOfOffers($article->getNumberOfOffers() + 1);

            // Persist the offer request and the updated article
            $entityManager->persist($offerRequest);
            $entityManager->persist($article); // Persist the updated article
            $entityManager->flush();

            $this->addFlash('success', 'Offer made successfully.');
            try {
                $email = (new Email())
                    ->from('agrebi3aziz@gmail.com')
                    ->to($articleOwner->getEmail()) // Send to article owner's email
                    ->subject('New Offer for Your Article')
                    ->text(sprintf(
                        "Hello %s,\n\nUser %s has made an offer for your article: %s.\n\nMessage:\n%s",
                        $articleOwner->getFirstname(),
                        $user instanceof \App\Entity\User && method_exists($user, 'getFirstname') ? $user->getFirstname() : 'User',
                        $article->getTitre(),
                        $form->get('message')->getData()
                    ));

                $this->mailer->send($email);
                $this->addFlash('success', 'Email sent successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to send email: ' . $e->getMessage());
            }
            return $this->redirectToRoute('app_request_index');
        }

        return $this->render('request/new.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    #[Route('/{id}', name: 'app_request_show', methods: ['GET'])]
    public function show(OfferRequest $offerRequest): Response
    {
        // Check if the logged-in user is the owner of the article (to display the buttons)
        $canManageRequest = false;
        if ($this->getUser() && $offerRequest->getArticleid()->getUser() === $this->getUser()) {
            $canManageRequest = true;
        }

        // Assuming you have a method to get the proposed article
        $articleProp = $offerRequest->getArticleprop(); // Modify this based on your actual model

        return $this->render('request/show.html.twig', [
            'request' => $offerRequest,
            'Articleid' => $offerRequest->getArticleid(), // Pass the article related to the request
            'articleprop' => $articleProp, // Pass the proposed article
            'canManageRequest' => $canManageRequest,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_request_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OfferRequest $offerRequest, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RequestType::class, $offerRequest);
        $form->handleRequest($request);

        // Get the referer URL (the previous page)
        $referer = $request->headers->get('referer');

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // If the referer is available, redirect to it
            if ($referer) {
                return $this->redirect($referer);
            }

            // Fallback if referer is not available
            return $this->redirectToRoute('app_request_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('request/edit.html.twig', [
            'request' => $offerRequest,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_request_delete', methods: ['POST'])]
    public function delete(Request $request, OfferRequest $offerRequest, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $offerRequest->getId(), $request->get('_token'))) {
            $entityManager->remove($offerRequest);
            $entityManager->flush();
        }

        // Get the referer (previous page URL)
        $referer = $request->headers->get('referer');

        // Redirect to the referer if available, otherwise fallback to 'app_request_index'
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_request_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/{id}/accept', name: 'app_request_accept', methods: ['POST'])]
    public function acceptRequest(
        OfferRequest $offerRequest,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Update the status of the accepted offer request
        $offerRequest->setStatus('accepted');

        // Update the article status
        $article = $offerRequest->getArticleid();
        $article->setStatus('swapped');

        // Retrieve all other requests for this article
        $offerRequests = $entityManager->getRepository(OfferRequest::class)
            ->findBy(['Articleid' => $article]);

        // Auto-refuse other requests
        foreach ($offerRequests as $req) {
            if ($req->getId() !== $offerRequest->getId() && $req->getStatus() !== 'accepted') {
                $req->setStatus('auto-refused');
            }
        }

        $entityManager->flush();

        // Notify the user who made the accepted offer
        try {
            $articleOwner = $article->getUser(); // Assuming `getUser()` fetches the owner
            $offerMaker = $offerRequest->getUserid(); // Assuming `getUserid()` fetches the offer maker

            $email = (new Email())
                ->from('agrebi3aziz@gmail.com')
                ->to($offerMaker->getEmail()) // Send email to the offer maker
                ->subject('Your Offer Has Been Accepted')
                ->text(sprintf(
                    "Hello %s,\n\nYour offer for the article '%s' has been accepted by the owner, %s.\nYou can contact them at their phone number: %s.\n\nThank you!",
                    $offerMaker->getFirstname(),
                    $article->getTitre(),
                    $articleOwner->getFirstname(),
                    $articleOwner->getPhonenum()
                ));

            $this->mailer->send($email);
            $this->addFlash('success', 'Email notification sent to the offer maker.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to send email notification: ' . $e->getMessage());
        }

        $this->addFlash('success', 'Request accepted.');

        return $this->redirectToRoute('app_request_index');
    }

    #[Route('/{id}/refuse', name: 'app_request_refuse', methods: ['POST'])]
    public function refuseRequest(OfferRequest $offerRequest, EntityManagerInterface $entityManager, Request $request): Response
    {
        $offerRequest->setStatus('refused');
        $entityManager->flush();

        $this->addFlash('success', 'Request refused.');

        // Get the referer (previous page URL)
        $referer = $request->headers->get('referer');

        // Redirect to the referer if available, otherwise fallback to 'app_request_received'
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_request_received');
    }
}
