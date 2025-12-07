<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Person;
use App\Form\PersonType;
use App\Repository\PersonRepository;
use App\Service\TreeFormatterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/arbre-genealogique', name: 'app_tree')]
#[IsGranted('ROLE_USER')]
class TreeController extends AbstractController
{
    /**
     * Ajoute une nouvelle personne et gère la persistance bidirectionnelle des relations.
     */
    #[Route('/ajouter-une-personne', name: '_new_person', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $person = new Person();
        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

       if ($form->isSubmitted() && $form->isValid()) {
            
            $person->setOwner($user);

            // --- GESTION DE LA PERSISTANCE BIDIRECTIONNELLE ---
            
            // 1. Mise à jour forcée des Enfants existants (pour mettre à jour leur collection 'parents')
            foreach ($person->getChildren() as $child) {
                if (!$child->getParents()->contains($person)) {
                    $child->addParent($person); // Logique de bidirectionnalité
                }
                // Si l'enfant existe déjà en base, on le persiste pour forcer le Change Tracking
                if ($child->getId() !== null) { 
                    $em->persist($child); 
                }
            }

            // 2. Mise à jour forcée des Parents existants (pour mettre à jour leur collection 'children')
            foreach ($person->getParents() as $parent) {
                if (!$parent->getChildren()->contains($person)) {
                    $parent->addChild($person); // Logique de bidirectionnalité
                }
                // Si le parent existe déjà en base, on le persiste pour forcer le Change Tracking
                if ($parent->getId() !== null) {
                    $em->persist($parent);
                }
            }

            // 3. On persiste la nouvelle personne.
            $em->persist($person);

            // Le flush enregistre toutes les entités suivies et modifiées.
            $em->flush(); 

            $this->addFlash('success', 'La nouvelle personne et ses liens de parenté ont été enregistrés.');

            return $this->redirectToRoute('app_tree_matrix'); // Redirection vers la matrice par défaut
        }

        return $this->render('tree/new_person.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }

    /**
     * Affiche la Matrice de Parenté (Vue Analytique).
     */
    #[Route('/', name: '_my_tree', methods: ['GET'])]
    public function matrix(PersonRepository $personRepository, TreeFormatterService $formatterService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Charger TOUTES les personnes de l'utilisateur pour construire la matrice complète
        $people = $personRepository->findBy(['owner' => $user], ['firstName' => 'ASC','lastName' => 'ASC', ]);
        
        if (empty($people)) {
            $this->addFlash('info', 'Veuillez ajouter votre première personne pour démarrer la matrice.');
            return $this->redirectToRoute('app_tree_new_person');
        }

        return $this->render('tree/my_tree.html.twig', [
            'people' => $people,
            'formatterService' => $formatterService, // Passage du service à Twig pour calculer les relations
        ]);
    }
}