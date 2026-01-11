<?php
namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Entity\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('/chart', name: 'chart')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Comptages globaux
        $totalUsers = $entityManager->getRepository(User::class)->count([]);
        $totalArticles = $entityManager->getRepository(Article::class)->count([]);
        $totalRequests = $entityManager->getRepository(Request::class)->count([]);

        // Récupération de tous les articles
        $articles = $entityManager->getRepository(Article::class)->findAll();

        $monthlyStats = [];
        $yearlyStats = [];
        $statusStats = [];

        foreach ($articles as $article) {
            $date = $article->getDate();
            $year = $date->format('Y');
            $month = $date->format('F'); // Nom complet du mois
            $status = $article->getStatus();

            $monthlyKey = $month . '/' . $year;
            $monthlyStats[$monthlyKey] = ($monthlyStats[$monthlyKey] ?? 0) + 1;

            $yearlyStats[$year] = ($yearlyStats[$year] ?? 0) + 1;

            $statusStats[$status] = ($statusStats[$status] ?? 0) + 1;
        }

        // Récupération de toutes les requests
        $requests = $entityManager->getRepository(Request::class)->findAll();

        $requestsByMonth = [];
        $requestsByYear = [];
        $requestsStatusStats = []; // ← initialisation pour éviter l'erreur

        foreach ($requests as $request) {
            $date = $request->getDatedemande();
            $status = $request->getStatus();
            $month = $date->format('F');
            $year = $date->format('Y');

            $monthlyKey = $month . '/' . $year;
            $requestsByMonth[$monthlyKey] = ($requestsByMonth[$monthlyKey] ?? 0) + 1;

            $requestsByYear[$year] = ($requestsByYear[$year] ?? 0) + 1;

            $requestsStatusStats[$status] = ($requestsStatusStats[$status] ?? 0) + 1;
        }

        // Préparation des données pour Twig
        return $this->render('dashboard/index.html.twig', [
            'articlesByMonth' => array_map(function ($month, $count) {
                return ['month' => $month, 'count' => $count];
            }, array_keys($monthlyStats), array_values($monthlyStats)),

            'articlesByYear' => array_map(function ($year, $count) {
                return ['year' => $year, 'count' => $count];
            }, array_keys($yearlyStats), array_values($yearlyStats)),

            'statusLabels' => array_keys($statusStats),
            'statusCounts' => array_values($statusStats),

            'requestsByMonth' => array_map(function ($month, $count) {
                return ['month' => $month, 'count' => $count];
            }, array_keys($requestsByMonth), array_values($requestsByMonth)),

            'requestsByYear' => array_map(function ($year, $count) {
                return ['year' => $year, 'count' => $count];
            }, array_keys($requestsByYear), array_values($requestsByYear)),

            'requestsStatusLabels' => array_keys($requestsStatusStats),
            'requestsStatusCounts' => array_values($requestsStatusStats),

            'totalUsers' => $totalUsers,
            'totalArticles' => $totalArticles,
            'totalRequests' => $totalRequests,
        ]);
    }
}
