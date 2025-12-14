<?php
// src/Controller/NotaireConsultationController.php

namespace App\Controller;

use App\Service\TemporaryAccessService;
use App\Service\SimulationPlanningService;
use App\Form\NotaireCodeConsultationType; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/notaire', name: 'notaire_')]
class NotaireController extends AbstractController
{
    public function __construct(
        private readonly TemporaryAccessService $accessService,
        private readonly SimulationPlanningService $planningService
    ) {
    }

    #[Route('/', name: 'dashboard')]
    public function ddashboard(): Response
    {
        return $this->render('notaire/dashboard.html.twig');
    }

    /**
     * Gère l'affichage du formulaire (GET) et la soumission du code (POST).
     */
    #[Route('/recherche/{code}', name: 'consultation_input', methods: ['GET', 'POST'])]
    public function consultInputAction(Request $request, ?string $code = null): Response
    {
        $viewData = [];
        $initialCode = $code; 
        $session = $request->getSession();
        
        // ⭐️ 1. VÉRIFICATION DU SUCCÈS PRÉCÉDENT EN SESSION (Si l'utilisateur clique sur le bouton "Retour" de la page de résultats)
        // Note: Nous n'avons pas besoin de cette vérification si le succès redirige vers 'consultation_show'.
        // Cependant, nous la laissons pour que le code soit complet et robuste.
        
        $form = $this->createForm(NotaireCodeConsultationType::class, ['uniqueCode' => $initialCode]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $inputCode = $form->get('uniqueCode')->getData(); 
            
            try {
                // Succès : Validation et récupération des données
                $user = $this->accessService->getUserByTemporaryCode($inputCode);
                $simulationData = $this->planningService->getSimulationPlan($user); 
                
                // 2. STOCKAGE EN SESSION
                $session->set('notaire_consultation_data', [
                    'simulationData' => $simulationData,
                    'clientEmail' => $user->getEmail(),
                    'uniqueCode' => $inputCode, 
                ]);
                
                $this->addFlash('success', 'La simulation a été trouvée.');
                
                // ⭐️ 3. REDIRECTION VERS LA NOUVELLE ACTION D'AFFICHAGE ⭐️
                // Le POST redirige vers la page GET dédiée à l'affichage.
                return $this->redirectToRoute('notaire_consultation_show', ['code' => $inputCode]);

            } catch (AccessDeniedException $e) {
                // ÉCHEC MÉTIER : Redirection vers la page d'entrée (elle-même)
                $this->addFlash('danger', $e->getMessage());
                
                return $this->redirectToRoute('notaire_consultation_input', ['code' => $inputCode ?? null]);
                
            } catch (\Exception $e) {
                // ÉCHEC INTERNE
                $this->addFlash('danger', 'Une erreur interne est survenue lors du calcul de la simulation.');
                return $this->redirectToRoute('notaire_consultation_input', ['code' => $inputCode ?? null]);
            }
        } 
        
        // Affichage du template d'entrée
        return $this->render('notaire/code_input.html.twig', array_merge($viewData, [
            'consultationForm' => $form->createView(),
            'initialCode' => $initialCode, 
        ]));
    }

    /**
     * Affiche le plan de consultation après un succès.
     */
    #[Route('/consultation/{code}', name: 'consultation_show', methods: ['GET'])]
    public function consultShowAction(Request $request, string $code): Response
    {
        $session = $request->getSession();
        $successData = $session->get('notaire_consultation_data');

        if ($successData && $successData['uniqueCode'] === $code) {
            
            // ⭐️ DONNÉES DISPONIBLES : Nettoyage et affichage ⭐️
            $session->remove('notaire_consultation_data'); 
            
            return $this->render('notaire/plan_synthetique.html.twig', [
                // Nous passons ces variables pour que le template puisse inclure 'plan_synthetique.html.twig'
                'plan' => $successData['simulationData'],
                'clientEmail' => $successData['clientEmail'],
                'initialCode' => $code,
            ]);

        } else {
            // CODE NON TROUVÉ EN SESSION (Erreur d'accès direct ou session expirée)
            $this->addFlash('warning', "Le plan de simulation n'est pas disponible. Veuillez saisir à nouveau le code.");
            
            // Redirection vers la page d'entrée
            return $this->redirectToRoute('notaire_consultation_input', ['code' => $code]);
        }
    }
}