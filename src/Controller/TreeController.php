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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        
        // 1. Création du formulaire sans l'option 'is_initial_creation' (qui est false par défaut)
        $person = new Person();
        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

       if ($form->isSubmitted() && $form->isValid()) {
            
            $person->setOwner($user);

            // --- GESTION DE LA PERSISTANCE BIDIRECTIONNELLE (Ajout) ---
            
            // 1. Mise à jour forcée des Enfants existants (pour mettre à jour leur collection 'parents')
            foreach ($person->getChildren() as $child) {
                // S'assurer que la relation bidirectionnelle est établie si l'enfant existe déjà
                if (!$child->getParents()->contains($person)) {
                    $child->addParent($person);
                }
                if ($child->getId() !== null) { 
                    $em->persist($child); 
                }
            }

            // 2. Mise à jour forcée des Parents existants (pour mettre à jour leur collection 'children')
            foreach ($person->getParents() as $parent) {
                // S'assurer que la relation bidirectionnelle est établie si le parent existe déjà
                if (!$parent->getChildren()->contains($person)) {
                    $parent->addChild($person);
                }
                if ($parent->getId() !== null) {
                    $em->persist($parent);
                }
            }

            // 3. Persistance de la nouvelle personne.
            $em->persist($person);

            $em->flush(); 

            $this->addFlash('success', 'La nouvelle personne et ses liens de parenté ont été enregistrés.');

            return $this->redirectToRoute('app_tree_my_tree');
        }

        return $this->render('tree/new_person.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }
    
    /**
     * Permet de consulter et modifier les détails et les relations d'une personne existante.
     */
    #[Route('/modifier-personne/{id}', name: '_edit_person', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Person $person, // Injection du paramètre dans l'entité
        EntityManagerInterface $em
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // 1. Vérification de la propriété (Sécurité)
        if ($person->getOwner() !== $user) {
            $this->addFlash('danger', 'Vous n\'avez pas la permission de modifier cette personne.');
            return $this->redirectToRoute('app_tree_my_tree');
        }
        
        // --- PRÉPARATION (pour gestion des relations retirées) ---
        // Cloner les collections originales avant que le formulaire ne les modifie
        $originalParents = clone $person->getParents();
        $originalChildren = clone $person->getChildren();
        
        // 2. Création du formulaire
        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 3. Gestion de la persistance bidirectionnelle et du nettoyage
            
            // a) Nettoyage des ANCIENNES relations retirées
            
            // Nettoyage des Parents : Parcourir les anciens parents qui ne sont PLUS dans la collection
            foreach ($originalParents as $parent) {
                if (!$person->getParents()->contains($parent)) {
                    $parent->removeChild($person); // Retirer la personne de la collection 'children' du parent
                    $em->persist($parent);
                }
            }

            // Nettoyage des Enfants : Parcourir les anciens enfants qui ne sont PLUS dans la collection
            foreach ($originalChildren as $child) {
                if (!$person->getChildren()->contains($child)) {
                    $child->removeParent($person); // Retirer la personne de la collection 'parents' de l'enfant
                    $em->persist($child);
                }
            }

            // b) Mise à jour des NOUVELLES relations ajoutées
            
            // Mise à jour forcée des Parents (pour mettre à jour leur collection 'children')
            foreach ($person->getParents() as $parent) {
                if (!$parent->getChildren()->contains($person)) {
                    $parent->addChild($person);
                }
                if ($parent->getId() !== null) { 
                    $em->persist($parent);
                }
            }
            
            // Mise à jour forcée des Enfants (pour mettre à jour leur collection 'parents')
            foreach ($person->getChildren() as $child) {
                if (!$child->getParents()->contains($person)) {
                    $child->addParent($person);
                }
                if ($child->getId() !== null) { 
                    $em->persist($child);
                }
            }

            // 4. Persistance de la personne modifiée
            $em->persist($person); 
            $em->flush(); 

            $this->addFlash('success', 'La personne ' . $person->getFirstName() . ' ' . $person->getLastName() . ' a été mise à jour.');

            return $this->redirectToRoute('app_tree_my_tree');
        }

        return $this->render('tree/edit_person.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }


    /**
     * Affiche la Matrice de Parenté (Vue Analytique), garantissant un minimum de 8 personnes.
     */
    #[Route('/', name: '_my_tree', methods: ['GET'])]
    public function matrix(
        PersonRepository $personRepository,
        TreeFormatterService $formatterService,
        #[Autowire('%app.tree.min_people_count%')] int $minPeople
        ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // 1. Récupérer TOUTES les personnes réelles de l'utilisateur
        $people = $personRepository->findBy(['owner' => $user], ['lastName' => 'ASC', 'firstName' => 'ASC']);
        $currentCount = count($people);

        // Si l'utilisateur n'a aucune personne, on le redirige pour qu'il crée la première.
        // Cette redirection est une sécurité, mais le Subscriber gère déjà l'interception.
        if ($currentCount === 0) {
            $this->addFlash('info', 'Veuillez ajouter votre première personne pour démarrer la matrice.');
            return $this->redirectToRoute('app_tree_initial_person_creation');
        }

        $peopleToShow = $people;

        // 2. Logique d'ajout de personnes bidons (mocks) si le compte est insuffisant
        if ($currentCount < $minPeople) {
            $mocksNeeded = $minPeople - $currentCount;
            
            for ($i = 0; $i < $mocksNeeded; $i++) {
                $mockPerson = new Person();
                // Ces personnes ne seront pas persistées, elles sont juste pour l'affichage.
                $mockPerson->setFirstName('Personne');
                $mockPerson->setLastName('Aléatoire ' . ($i + 1));
                
                $peopleToShow[] = $mockPerson;
            }
        }
        
        // 3. Garantir que la personne initiale est la première dans la liste (UX)
        // La personne initiale est la première créée (triée par ID croissant).
        $initialPerson = $personRepository->findOneBy(['owner' => $user], ['id' => 'ASC']);
        
        if ($initialPerson) {
            // Filtrer la liste pour retirer l'initialPerson si elle est déjà dans le tableau,
            // puis la placer en première position.
            $peopleToShow = array_values(array_filter($peopleToShow, function ($person) use ($initialPerson) {
                // On compare les IDs (seul moyen fiable d'exclure les vraies personnes).
                return $person->getId() !== $initialPerson->getId();
            }));
            
            array_unshift($peopleToShow, $initialPerson);
        }
        
        return $this->render('tree/my_tree.html.twig', [
            'people' => $peopleToShow,
            'formatterService' => $formatterService,
        ]);
    }

    /**
     * Force l'utilisateur à renseigner sa première personne après l'inscription.
     */
    #[Route('/creation-personne-initiale', name: '_initial_person_creation')]
    public function createInitialPerson(Request $request, EntityManagerInterface $entityManager, PersonRepository $personRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // 1. Vérifier si l'utilisateur a déjà une personne.
        if ($personRepository->findOneBy(['owner' => $user])) {
            // S'il a déjà une personne, on le redirige directement vers la matrice.
            return $this->redirectToRoute('app_tree_my_tree'); 
        }

        $person = new Person();
        $person->setOwner($user); 

        // 2. Création du formulaire AVEC l'option pour masquer les relations
        $form = $this->createForm(PersonType::class, $person, [
            'is_initial_creation' => true, 
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($person);
            $entityManager->flush();
            $this->addFlash('success', 'Votre profil initial a été créé avec succès ! Vous pouvez maintenant ajouter de nouvelles relations.');
            // Redirection vers la matrice qui sera maintenant remplie (avec minimum 8 personnes)
            return $this->redirectToRoute('app_tree_my_tree'); 
        }

        return $this->render('tree/create_initial_person.html.twig', [
            'personForm' => $form,
        ]);
    }
}