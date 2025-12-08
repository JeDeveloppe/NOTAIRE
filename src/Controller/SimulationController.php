<?php

namespace App\Controller;

use App\Service\SimulationPlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/simulation')]
#[IsGranted('ROLE_USER')]
class SimulationController extends AbstractController
{
    /**
     * Affiche la planification des cycles vue du point de vue des DONATEURS.
     */
    #[Route('/donateurs', name: 'app_simulation_planning_donateurs', methods: ['GET'])]
    public function planningDonateursIndex(SimulationPlanningService $simulationPlanningService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Le plan est structuré Donateur -> Bénéficiaire par défaut
        $simulationPlan = $simulationPlanningService->getSimulationPlan($user);

        return $this->render('simulation/planning_donateurs.html.twig', [
            'plan' => $simulationPlan,
        ]);
    }

    /**
     * Affiche la planification des cycles vue du point de vue des BÉNÉFICIAIRES.
     * C'est ici que vous utiliserez le template réorganisé dans la discussion précédente.
     */
    #[Route('/donataires', name: 'app_simulation_planning_donataires', methods: ['GET'])]
    public function planningBeneficiairesIndex(SimulationPlanningService $simulationPlanningService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Le service renvoie les données, que le template va restructurer (en Twig) ou que le service pourrait déjà restructurer (en PHP).
        $simulationPlan = $simulationPlanningService->getSimulationPlan($user);

        return $this->render('simulation/planning_donataires.html.twig', [
            'plan' => $simulationPlan, // Contient les données brutes Donateur -> Bénéficiaire
        ]);
    }
}