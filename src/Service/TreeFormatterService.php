<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\User;
use App\Repository\PersonRepository;

class TreeFormatterService
{
    public function __construct(private PersonRepository $personRepository)
    {
    }

    public function getTreeDataForUser(User $user): array
    {
        /** @var Person[] $allPersons */
        $allPersons = $this->personRepository->findBy(['owner' => $user]);
        $allNodes = []; 
        $isChildOrSpouse = []; // Suit qui a été attaché (n'est PAS une racine)

        // 1. Initialiser tous les nœuds formatés et la map de suivi
        foreach ($allPersons as $person) {
            $personId = $person->getId();
            $allNodes[$personId] = $this->formatPersonData($person, $user);
            $isChildOrSpouse[$personId] = false; // Par défaut, tout le monde est une racine potentielle
        }

        // 2. Parcourir les PERSONNES COMME PARENTS pour construire la hiérarchie
        foreach ($allPersons as $parent) {
            $parentId = $parent->getId();

            /** @var Person $child */
            foreach ($parent->getChildren() as $child) {
                $childId = $child->getId();
                
                // S'assurer que l'enfant est bien dans la collection de l'utilisateur
                if (isset($allNodes[$childId])) {
                    
                    // 2a. Marquer l'enfant comme étant attaché (donc non une racine)
                    $isChildOrSpouse[$childId] = true; 
                    
                    // 2b. L'attacher au parent
                    $allNodes[$parentId]['children'][] = $allNodes[$childId];
                    
                    // 3. Identification et attachement du Conjoint (Marie à Jean)
                    // On ne le fait que si l'enfant a deux parents
                    if ($child->getParents()->count() > 1) {
                        
                        /** @var Person $potentialSpouse */
                        foreach ($child->getParents() as $potentialSpouse) {
                            $spouseId = $potentialSpouse->getId();
                            
                            // Si l'autre parent est bien dans la collection et n'est pas déjà le conjoint du parent
                            if ($spouseId !== $parentId && 
                                isset($allNodes[$spouseId]) && 
                                !isset($allNodes[$parentId]['spouse'])
                            ) {
                                // Attacher le conjoint (Marie) au parent principal (Jean)
                                $allNodes[$parentId]['spouse'] = $allNodes[$spouseId];
                                
                                // Marquer le conjoint comme étant attaché (donc non une racine séparée)
                                $isChildOrSpouse[$spouseId] = true;
                                
                                // On a trouvé le conjoint, on arrête de chercher les autres parents de l'enfant.
                                break; 
                            }
                        }
                    }
                }
            }
        }
        
        // 4. Filtrer et retourner les vraies racines (Ceux qui ne sont ni enfant, ni conjoint)
        $finalRootNodes = [];
        foreach ($allNodes as $id => $node) {
            if (!$isChildOrSpouse[$id]) {
                $finalRootNodes[] = $node;
            }
        }

        return $finalRootNodes;
    }
    
    // Fonction utilitaire inchangée
    private function formatPersonData(Person $person, User $user): array
    {
        $isDeceased = $person->getDateOfDeath() !== null;
        $className = $isDeceased ? 'deceased-node' : 'living-node';

        // Identification de l'utilisateur 'Alice'
        if ($person->getFirstName() === 'Alice') {
            $className .= ' current-user-node';
        }
        
        return [
            'id' => $person->getId(),
            'name' => $person->getFirstName() . ' ' . $person->getLastName(),
            'title' => $isDeceased ? 'Décédé' : 'Vivant',
            'dob' => $person->getDateOfBirth() ? $person->getDateOfBirth()->format('d/m/Y') : 'N/A',
            'className' => $className,
            'children' => []
        ];
    }
}