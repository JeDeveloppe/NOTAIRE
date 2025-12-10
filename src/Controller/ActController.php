<?php

namespace App\Controller;

use App\Entity\Act; 
use App\Form\ActType; 
use App\Service\ActService;
use App\Repository\ActRepository;
use App\Repository\TypeActRepository;
use Doctrine\ORM\EntityManagerInterface; 
use Symfony\Component\HttpFoundation\Request; 
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/act')]
#[IsGranted('ROLE_USER')]
class ActController extends AbstractController
{
    // Constante pour le drapeau de session
    public const SIMULATION_CHECK_COMPLETED = 'simulation_check_completed'; 

    private array $abatementAmountsInCents;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->abatementAmountsInCents = $parameterBag->get('app.fiscal_abatement_amounts_in_cents'); 
    }
    
    /**
     * Affiche la liste des actes de donation déjà déclarés et gère l'ajout d'un nouvel acte.
     * C'est le point de passage OBLIGATOIRE avant la simulation.
     */
    #[Route('/liste-des-acts-connus', name: 'app_acts_declared', methods: ['GET', 'POST'])]
    public function actsdeclared(
        ActRepository $actRepository, 
        SessionInterface $session,
        EntityManagerInterface $em,
        Request $request,
        ActService $actService,
    ): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // 1. --- GESTION DU FORMULAIRE D'AJOUT ---
        $newAct = new Act();
        $form = $this->createForm(ActType::class, $newAct); 
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $valueInEuros = $newAct->getValue(); 
            $valueInCentimes = (int) round($valueInEuros * 100); 
            $newAct->setValue($valueInCentimes); 
            
            // Calcul de l'Abattement Consommé (en centimes)
            $consumed = $actService->calculateConsumedAbatement(
                $newAct->getTypeOfAct(), 
                $newAct->getValue()
            );
            $newAct->setConsumedAbatement($consumed);

            // Liaison à l'utilisateur
            $newAct->setOwner($user); 
            
            $em->persist($newAct);
            $em->flush();
            
            $this->addFlash('success', 'L\'acte de donation a été enregistré avec succès.');
            
            return $this->redirectToRoute('app_acts_declared'); 
        }
        
        // 2. Récupérer les actes de donation déjà déclarés par cet utilisateur
        $existingActs = $actRepository->findBy(['owner' => $user], ['dateOfAct' => 'DESC']);
        
        // 3. LOGIQUE DE VÉRIFICATION POUR LE BOUTON DE SIMULATION
        // On suppose que l'utilisateur (le Donateur Principal) est lui-même une entité Personne.
        // Il doit y avoir au moins lui (1) + un Bénéficiaire (1) = 2 Personnes.
        
        // ⚠️ NOTE : La méthode `countUserPeople` est hypothétique. Adaptez-la à votre repository.
        $totalPersonnes = $user->getPeopleOwned()->count();
        $canLaunchSimulation = $totalPersonnes >= 2; 

        // 4. POSER LE DRAPEAU DE SESSION
        $session->set(self::SIMULATION_CHECK_COMPLETED, true); 
        
        // 5. Rendu du template
        return $this->render('acts/acts_declared.html.twig', [
            'existingActs' => $existingActs,
            'actForm' => $form->createView(), 
            'canLaunchSimulation' => $canLaunchSimulation, // ⬅️ Variable passée au template
        ]);
    }
    
    /**
     * Affiche le lexique des règles fiscales et des définitions des TypeAct.
     */
    #[Route('/lexique-fiscal', name: 'app_acts_fiscal_summary', methods: ['GET'])]
    public function fiscalActsSummary(TypeActRepository $typeActRepository, ActService $actService): Response
    {
        // 1. Récupère tous les types d'actes qui contiennent des règles fiscales définies
        $fiscalActs = $typeActRepository->findBy(['code' => [
            \App\Service\ActService::CODE_CLASSIQUE, 
            \App\Service\ActService::CODE_SARKOZY
        ]], ['name' => 'ASC']);
        
        // 2. Rendu du template
        return $this->render('simulation/fiscal_acts_summary.html.twig', [
            'fiscalActs' => $fiscalActs, 
            'abatementAmountsInCents' => $this->abatementAmountsInCents,
        ]);
    }
}