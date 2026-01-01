<?php

namespace App\Controller;

use App\Service\DonationService;
use App\Service\OptimizationService;
use App\Service\SimulationService;
use App\Service\TaxCalculatorService;
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
        private OptimizationService $optimizationService,
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
            'never_used'      => $analysis['never_used'],
            'totalAvailable' => $dashboardStats['totalAvailableAllowance'] ?? 0,
        ]);
    }

    #[Route('/optimisations-possibles', name: 'app_optimization_dashboard')]
    public function futureDashboard(
        Request $request, 
        SimulationService $simulationService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $people = $user->getPeople();
        if (count($people) < 2) {
            $this->addFlash('info', 'Ajoutez au moins un bénéficiaire pour débloquer l\'analyse fiscale.');
            return $this->redirectToRoute('app_family_dashboard');
        }

        $simulation = $simulationService->getOrCreateSimulation($user);

        $data = $this->optimizationService->getSimulationDatas($request->query->get('date'), $user);

        return $this->render('family/optimization/simuled_opportunities.html.twig', [
            'activePeriods'  => $data['analysis']['active_periods'],
            'familyPlan'     => $data['familyPlan'],
            'totalAvailable' => $data['totalAvailable'],
            'totalTaxSaving' => $data['totalSaving'],
            'referenceDate'  => $data['referenceDate'],
            'simulation'     => $simulation, // On passe l'objet pour afficher la réf et l'historique
        ]);
    }
}
