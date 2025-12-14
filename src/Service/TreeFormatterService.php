<?php

namespace App\Service;

use App\Entity\Person;
use Doctrine\Common\Collections\Collection;

class TreeFormatterService
{
    // =========================================================================
    // I. MÉTHODE POUR LA VUE MATRICE (Calcul des Relations Fiscales)
    // =========================================================================

    /**
     * Détermine la relation principale entre deux personnes A et B pour le calcul fiscal.
     * Inclut maintenant les relations de 2e degré (Grand-Parent / Petit-Enfant).
     * * @param Person $personA Le Donateur potentiel
     * @param Person $personB Le Bénéficiaire potentiel
     * @return string La nature de la relation (Parent, Enfant, Grand-Parent, Petit-Enfant, Frère/Sœur, Inconnu).
     */
    public function getRelationship(Person $personA, Person $personB): string
    {
        if ($personA->getId() === $personB->getId()) {
            return 'Self';
        }

        // --- 1. Relations de 1er degré ---
        
        // A est-il Parent de B ?
        if ($personB->getParents()->contains($personA)) {
            return 'Parent';
        }
        
        // A est-il Enfant de B ?
        if ($personA->getParents()->contains($personB)) {
            return 'Enfant';
        }

        // --- 2. Relations de Fratrie ---
        
        /** @var Collection<int, Person> $aParents */
        $aParents = $personA->getParents();
        /** @var Collection<int, Person> $bParents */
        $bParents = $personB->getParents();
        
        $aParentsIds = $aParents->map(fn(Person $p) => $p->getId())->toArray();
        $bParentsIds = $bParents->map(fn(Person $p) => $p->getId())->toArray();
        
        if (!empty(array_intersect($aParentsIds, $bParentsIds))) {
            return 'Frère/Sœur';
        }
        
        // --- 3. Relations de 2e degré (Grand-Parent / Petit-Enfant) ---
        
        // Vérifier si A est le Grand-parent de B (Parcours de deux niveaux vers le haut à partir de B)
        foreach ($personB->getParents() as $parentOfB) {
            if ($parentOfB->getParents()->contains($personA)) {
                return 'Grand-Parent';
            }
        }
        
        // Vérifier si A est le Petit-enfant de B (Parcours de deux niveaux vers le haut à partir de A)
        // C'est l'inverse de la vérification précédente.
        foreach ($personA->getParents() as $parentOfA) {
            if ($parentOfA->getParents()->contains($personB)) {
                return 'Petit-Enfant';
            }
        }
        
        // --- 4. Autres ---
        
        return 'Inconnu';
    }


    // =========================================================================
    // II. MÉTHODE POUR LA VUE GRAPHE VIS.JS (Relations Directes pour Lazy Loading)
    // =========================================================================

    /**
     * Retourne uniquement les nœuds et arêtes des relations directes (parents, enfants, frères/sœurs)
     * d'une personne donnée, pour le chargement progressif dans Vis.js.
     * * @param Person $centerPerson La personne autour de laquelle les relations doivent être chargées.
     * @return array{'nodes': array, 'edges': array}
     */
    public function getDirectRelations(Person $centerPerson): array
    {
        $nodes = [];
        $edges = [];
        // Utilisé pour éviter d'ajouter plusieurs fois la même personne (surtout pour la personne centrale)
        $processedIds = []; 

        // 1. Ajouter la personne centrale (racine du niveau d'affichage)
        $this->addPersonToCollections($centerPerson, $nodes, 'center');
        $processedIds[] = $centerPerson->getId();

        // 2. Ajouter les Parents et les liens (Niveau au-dessus)
        foreach ($centerPerson->getParents() as $parent) {
            if (!in_array($parent->getId(), $processedIds)) {
                $this->addPersonToCollections($parent, $nodes, 'parent');
                $processedIds[] = $parent->getId();
            }
            $this->addEdge($parent, $centerPerson, $edges, 'Parenté'); // Parent -> Enfant
        }

        // 3. Ajouter les Enfants et les liens (Niveau au-dessous)
        foreach ($centerPerson->getChildren() as $child) {
            if (!in_array($child->getId(), $processedIds)) {
                $this->addPersonToCollections($child, $nodes, 'child');
                $processedIds[] = $child->getId();
            }
            $this->addEdge($centerPerson, $child, $edges, 'Parenté'); // Parent -> Enfant
        }
        
        // 4. Ajouter les Frères/Sœurs et leurs liens aux parents (Niveau latéral)
        foreach ($centerPerson->getParents() as $parent) {
            foreach ($parent->getChildren() as $sibling) {
                // Ne pas inclure la personne centrale elle-même
                if ($sibling->getId() !== $centerPerson->getId() && !in_array($sibling->getId(), $processedIds)) {
                    $this->addPersonToCollections($sibling, $nodes, 'sibling');
                    $processedIds[] = $sibling->getId();
                    // Lier le frère/sœur à son parent (Parent -> Frère/Sœur)
                    $this->addEdge($parent, $sibling, $edges, 'Parenté');
                }
            }
        }

        return [
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
        ];
    }
    
    // =========================================================================
    // III. FONCTIONS D'AIDE PRIVÉES POUR VIS.JS
    // =========================================================================

    /**
     * Ajoute une personne formatée à la collection de nœuds Vis.js.
     */
    private function addPersonToCollections(Person $person, array &$nodes, string $group): void
    {
        $label = $person->getFirstName() . ' ' . $person->getLastName() . "\n" 
               . 'Né(e) le ' . ($person->getDateOfBirth() ? $person->getDateOfBirth()->format('d/m/Y') : 'Inconnue');

        $title = 'Parents: ' . $person->getParents()->count() . ' - Enfants: ' . $person->getChildren()->count();

        $nodes[$person->getId()] = [
            'id'    => $person->getId(),
            'label' => $label,
            'title' => $title,
            'shape' => 'box',
            'group' => $group,
            'color' => $this->getNodeColor($group),
            'font'  => ['multi' => 'html', 'size' => 14],
        ];
    }

    /**
     * Ajoute une arête (lien) formatée à la collection d'arêtes Vis.js.
     */
    private function addEdge(Person $from, Person $to, array &$edges, string $label): void
    {
        // Utilisation d'une clé d'arête unique pour éviter les doublons dans les collections
        $edgeKey = min($from->getId(), $to->getId()) . '_' . max($from->getId(), $to->getId()) . '_P';

        if (!isset($edges[$edgeKey])) {
            $edges[$edgeKey] = [
                'id'    => $edgeKey,
                'from'  => $from->getId(),
                'to'    => $to->getId(),
                'arrows'=> 'to',
                'label' => $label,
                'color' => ['color' => '#007bff', 'highlight' => '#ffc107'],
                'font'  => ['align' => 'top'],
            ];
        }
    }
    
    /**
     * Définit la couleur du nœud en fonction de son groupe pour l'UX Vis.js.
     */
    private function getNodeColor(string $group): array
    {
        return match ($group) {
            'center' => ['background' => '#ffc107', 'border' => '#dc3545'],
            'parent' => ['background' => '#d4edda', 'border' => '#28a745'],
            'child'  => ['background' => '#e9f7ff', 'border' => '#007bff'],
            'sibling'=> ['background' => '#fff3cd', 'border' => '#ffc107'],
            default  => ['background' => '#f8f9fa', 'border' => '#6c757d'],
        };
    }
}