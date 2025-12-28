<?php

namespace App\Controller;

use App\Service\DonationService;
use App\Service\TaxCalculatorService;
use App\Service\TaxOptimizationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/famille/optimisation')]
#[IsGranted('ROLE_USER')]
class OptimizationController extends AbstractController
{
    public function __construct(
        private TaxOptimizationService $optimizationService,
        private DonationService $donationService,
        private TaxCalculatorService $taxService
    ) {}

    #[Route('/opportunites-manquees', name: 'app_optimization_missed')]
    public function missedOpportunities(): Response
    {
        $user = $this->getUser();

        // On récupère l'analyse complète (Actif vs Purgé)
        $analysis = $this->optimizationService->getDonationAnalyses($user);

        // Stats complémentaires pour le bandeau latéral
        $dashboardStats = $this->donationService->getUserDashboardStats($user);

        return $this->render('family/optimization/missed_opportunities.html.twig', [
            'expiredPeriods' => $analysis['expired_periods'], // Anciens dons (Perdus)
            'totalMissed'    => $analysis['total_missed'],
            'totalAvailable' => $dashboardStats['totalAvailableAllowance'] ?? 0,
        ]);
    }

    #[Route('/optimisations-possibles', name: 'app_optimization_dashboard')]
    public function futureDashboard(Request $request): Response
    {
        $user = $this->getUser();

        // Récupération de la date de simulation depuis l'URL
        $dateParam = $request->query->get('date');
        $referenceDate = $dateParam ? new \DateTimeImmutable($dateParam) : new \DateTimeImmutable();

        $analysis = $this->optimizationService->getDonationAnalyses($user);

        // On génère le plan à la date choisie
        $familyPlan = $this->optimizationService->getGlobalFamilyPlan($user, $referenceDate);
        $totalAvailable = array_sum(array_column($familyPlan, 'available'));

        // Calcul des économies fiscales totales possibles
        $totalSaving = 0;
        foreach ($familyPlan as $item) {
            $totalSaving += $this->taxService->calculateSaving(
                $item['available'], 
                $item['relationship_code']
            );
        }

        return $this->render('family/optimization/simuled_opportunities.html.twig', [
            'activePeriods'  => $analysis['active_periods'],
            'familyPlan'     => $familyPlan,
            'totalAvailable' => $totalAvailable,
            'totalTaxSaving' => $totalSaving,
            'referenceDate'  => $referenceDate, // Utile pour l'affichage
        ]);
    }
}
