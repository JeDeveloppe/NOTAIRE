<?php

namespace App\Controller;

use App\Service\SimulationPlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Controller\ActController;

#[Route('/simulation')]
#[IsGranted('ROLE_USER')]
class SimulationController extends AbstractController
{
    /**
     * Affiche la planification des cycles vue du point de vue des BÉNÉFICIAIRES.
     * C'est ici que vous utiliserez le template réorganisé dans la discussion précédente.
     */
    #[Route('/donataires', name: 'app_simulation_planning_donataires', methods: ['GET'])]
    public function planningBeneficiairesIndex(SimulationPlanningService $simulationPlanningService, SessionInterface $session): Response
    {
        // ⭐ VÉRIFICATION DU DRAPEAU : Si non trouvé, on redirige vers la déclaration obligatoire.
        if (!$session->get(ActController::SIMULATION_CHECK_COMPLETED)) {
            $this->addFlash('warning', 'Veuillez d\'abord vérifier et confirmer l\'état de vos donations passées.');
            return $this->redirectToRoute('app_acts_declared'); 
        }
        
        // OPTIONNEL : Effacer le drapeau pour forcer la vérification à la prochaine visite directe.
        $session->remove(ActController::SIMULATION_CHECK_COMPLETED);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Le service renvoie les données, que le template va restructurer (en Twig) ou que le service pourrait déjà restructurer (en PHP).
        $simulationPlan = $simulationPlanningService->getSimulationPlan($user);

        return $this->render('simulation/planning_donataires.html.twig', [
            'plan' => $simulationPlan, // Contient les données brutes Donateur -> Bénéficiaire
        ]);
    }
}