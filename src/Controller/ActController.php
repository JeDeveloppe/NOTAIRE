<?php

namespace App\Controller;

use App\Entity\Act; 
use App\Form\ActType; 
use App\Service\ActService;
use App\Repository\ActRepository;
use App\Repository\TypeActRepository;
use App\Repository\FiscalAbatementRuleRepository; // <-- NOUVEL IMPORT
use Doctrine\ORM\EntityManagerInterface; 
use Symfony\Component\HttpFoundation\Request; 
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface; // <-- SUPPRIMÉ

#[Route('/act')]
#[IsGranted('ROLE_USER')]
class ActController extends AbstractController
{
    public const SIMULATION_CHECK_COMPLETED = 'simulation_check_completed'; 

    // private array $abatementAmountsInCents; // <-- SUPPRIMÉ

    // Changement du constructeur : Injection du Repository
    public function __construct(
        private readonly FiscalAbatementRuleRepository $abatementRuleRepository // <-- NOUVELLE INJECTION
    )
    {
        // $this->abatementAmountsInCents = $parameterBag->get('app.fiscal_abatement_amounts_in_cents'); // <-- SUPPRIMÉ
    }
    
    /**
     * Affiche la liste des actes de donation déjà déclarés et gère l'ajout d'un nouvel acte.
     */
    #[Route('/liste-des-acts-connus', name: 'app_acts_declared', methods: ['GET', 'POST'])]
    public function actsdeclared(
        ActRepository $actRepository, 
        SessionInterface $session,
        EntityManagerInterface $em,
        Request $request,
        ActService $actService,
        // Suppression de ParameterBagInterface si elle n'est pas utilisée ailleurs dans cette méthode
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
            // NOTE: Si ActService dépend de l'entité FiscalAbatementRule, il doit aussi
            // être refactorisé pour utiliser le repository.
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
        $totalPersonnes = $user->getPeopleOwned()->count();
        $canLaunchSimulation = $totalPersonnes >= 2; 

        // 4. POSER LE DRAPEAU DE SESSION
        $session->set(self::SIMULATION_CHECK_COMPLETED, true); 
        
        // 5. Rendu du template
        return $this->render('acts/acts_declared.html.twig', [
            'existingActs' => $existingActs,
            'actForm' => $form->createView(), 
            'canLaunchSimulation' => $canLaunchSimulation, 
        ]);
    }
    
    /**
     * Affiche le lexique des règles fiscales et des définitions des TypeAct.
     */
    #[Route('/lexique-fiscal', name: 'app_acts_fiscal_summary', methods: ['GET'])]
    public function fiscalActsSummary(TypeActRepository $typeActRepository): Response
    {
        // 1. Récupère tous les types d'actes
        $fiscalActs = $typeActRepository->findBy(['code' => [
            \App\Service\ActService::CODE_CLASSIQUE, 
            \App\Service\ActService::CODE_SARKOZY
        ]], ['name' => 'ASC']);
        
        // 2. Récupère toutes les règles d'abattement depuis la base de données
        // Cela remplace l'ancien $this->abatementAmountsInCents
        $allAbatementRules = $this->abatementRuleRepository->findAll();

        // Si le template a besoin d'un tableau indexé par la clé de lien ('parent_enfant', etc.),
        // vous pouvez transformer le résultat ici:
        $abatementAmountsByLink = [];
        foreach ($allAbatementRules as $rule) {
            // Utilise le typeOfLink comme clé (ex: 'parent_enfant')
            $abatementAmountsByLink[$rule->getTypeOfLink()] = $rule->getAmountInCents();
        }

        // 3. Rendu du template
        return $this->render('acts/fiscal_acts_summary.html.twig', [
            'fiscalActs' => $fiscalActs, 
            'allAbatementRules' => $allAbatementRules, // Passage de toutes les règles (plus complet)
            'abatementAmountsByLink' => $abatementAmountsByLink, // (Optionnel) pour la compatibilité du template
        ]);
    }
}