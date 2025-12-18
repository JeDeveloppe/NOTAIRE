<?php

namespace App\Controller;

use App\Entity\NotaryOffice;
use App\Form\NotaryOfficeType;
use App\Service\NotaryService;
use App\Service\TemporaryAccessService;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\NotaireCodeConsultationType;
use App\Service\SimulationPlanningService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/notaire', name: 'notaire_')]
class NotaireController extends AbstractController
{
    public function __construct(
        private readonly TemporaryAccessService $accessService,
        private readonly SimulationPlanningService $planningService
    ) {
    }
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(NotaryService $potentielService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notaryOffice = $user->getNotaryOffice();
        
        $potentialClients = 0;
        
        // On ne calcule le potentiel que si l'étude ET la ville sont renseignées
        if ($notaryOffice && $notaryOffice->getCity()) {
            $potentialClients = $potentielService->getPotentielClients($notaryOffice);
        }
        
        return $this->render('notaire/dashboard.html.twig', [
            'potentialClients' => $potentialClients,
            'notaryOffice' => $notaryOffice // Peut être null
        ]);
    }

    /**
     * Gère l'affichage du formulaire de code et la soumission.
     */
    #[Route('/recherche/{code}', name: 'consultation_input', methods: ['GET', 'POST'])]
    public function consultInputAction(Request $request, ?string $code = null): Response
    {
        $initialCode = $code; 
        $session = $request->getSession();
        
        $form = $this->createForm(NotaireCodeConsultationType::class, ['uniqueCode' => $initialCode]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $inputCode = $form->get('uniqueCode')->getData(); 
            
            try {
                $user = $this->accessService->getUserByTemporaryCode($inputCode);
                $simulationData = $this->planningService->getSimulationPlan($user); 
                
                $session->set('notaire_consultation_data', [
                    'simulationData' => $simulationData,
                    'clientEmail' => $user->getEmail(),
                    'uniqueCode' => $inputCode, 
                ]);
                
                $this->addFlash('success', 'La simulation a été trouvée.');
                return $this->redirectToRoute('notaire_consultation_show', ['code' => $inputCode]);

            } catch (AccessDeniedException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('notaire_consultation_input', ['code' => $inputCode]);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors du calcul de la simulation.');
                return $this->redirectToRoute('notaire_consultation_input', ['code' => $inputCode]);
            }
        } 
        
        return $this->render('notaire/code_input.html.twig', [
            'consultationForm' => $form->createView(),
            'initialCode' => $initialCode, 
        ]);
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
            $session->remove('notaire_consultation_data'); 
            
            return $this->render('notaire/plan_synthetique.html.twig', [
                'plan' => $successData['simulationData'],
                'clientEmail' => $successData['clientEmail'],
                'initialCode' => $code,
            ]);
        }

        $this->addFlash('warning', "Le plan n'est plus disponible. Veuillez ressaisir le code.");
        return $this->redirectToRoute('notaire_consultation_input', ['code' => $code]);
    }

    #[Route('/mon-etude', name: 'edit_office', methods: ['GET', 'POST'])]
    public function editOffice(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notaryOffice = $user->getNotaryOffice();

        // 1. Récupérer la valeur du paramètre défini dans services.yaml
        $defaultRadius = (int) $this->getParameter('app.default_notary_radius');

        if (!$notaryOffice) {
            $notaryOffice = new NotaryOffice();
            $notaryOffice->setUser($user);
            // On peut même initialiser le rayon à la création
            $notaryOffice->setRadius($defaultRadius);
        }

        $form = $this->createForm(NotaryOfficeType::class, $notaryOffice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 2. Utiliser la variable au lieu du chiffre 25
            if (!$notaryOffice->isPremium()) {
                $notaryOffice->setRadius($defaultRadius);
            }

            $em->persist($notaryOffice);
            $em->flush();

            $this->addFlash('success', 'Les informations de votre étude ont été mises à jour.');
            return $this->redirectToRoute('notaire_dashboard');
        }

        return $this->render('notaire/edit_office.html.twig', [
            'form' => $form->createView(),
            'notaryOffice' => $notaryOffice,
            'defaultRadius' => $defaultRadius // Optionnel : pour l'utiliser dans Twig si besoin
        ]);
    }

    #[Route('/prospects-locaux', name: 'local_clients', methods: ['GET'])]
    public function localClients(NotaryService $notaryService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notaryOffice = $user->getNotaryOffice();

        if (!$notaryOffice || !$notaryOffice->getCity()) {
            $this->addFlash('warning', 'Veuillez configurer votre étude pour voir les prospects.');
            return $this->redirectToRoute('notaire_edit_office');
        }

        // On récupère la liste des utilisateurs dans le rayon
        $prospects = $notaryService->getPotentielClients($notaryOffice);

        return $this->render('notaire/local_clients.html.twig', [
            'prospects' => $prospects,
            'notaryOffice' => $notaryOffice
        ]);
    }
}