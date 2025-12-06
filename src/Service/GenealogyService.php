<?php

namespace App\Service;

use App\Entity\Person;

class GenealogyService
{
    private array $codes;

    /**
     * @param array $relationshipCodes Le tableau de codes injecté depuis config/services.yaml
     */
    public function __construct(array $relationshipCodes)
    {
        // Récupère le tableau: ['GRAND_PARENT_PETIT_ENFANT' => 'R_GPPE', ...]
        $this->codes = $relationshipCodes;
    }

    /**
     * Détermine la clé de relation fiscale entre un Donateur (A) et un Bénéficiaire (B).
     */
    public function getRelationshipKey(Person $donor, Person $beneficiary): string
    {
        // 1. Ligne Directe de 1er degré (Parent/Enfant)
        if ($this->isDirectParentOrChild($donor, $beneficiary)) {
            return $this->codes['PARENT_ENFANT']; // Retourne 'R_PE'
        }

        // 2. Ligne Directe de 2e degré (Grand-Parent/Petit-Enfant)
        if ($this->isGrandParentOrGrandChild($donor, $beneficiary)) {
            return $this->codes['GRAND_PARENT_PETIT_ENFANT']; // Retourne 'R_GPPE'
        }

        // 3. Ligne Collatérale de 2e degré (Frère/Sœur)
        if ($this->areSiblings($donor, $beneficiary)) {
            return $this->codes['FRERE_SOEUR']; // Retourne 'R_FS'
        }
        
        // 4. Ligne Collatérale de 3e degré (Oncle/Tante - Neveu/Nièce)
        if ($this->isUncleAuntNephewNiece($donor, $beneficiary)) {
            return $this->codes['ONCLE_NEVEU']; // Retourne 'R_ON'
        }

        // 5. Cas par défaut : Tiers ou Parenté très éloignée
        return $this->codes['TIERS']; // Retourne 'R_T'
    }

    // -------------------------------------------------------------------------
    // --- LOGIQUE PURE (méthodes privées basées sur Person.parents/children) ---
    // -------------------------------------------------------------------------

    private function isDirectParentOrChild(Person $a, Person $b): bool
    {
        // Teste si A est parent de B, OU si B est parent de A
        return $a->getChildren()->contains($b) || $b->getChildren()->contains($a);
    }
    
    private function isGrandParentOrGrandChild(Person $a, Person $b): bool
    {
        // A est Grand-Parent de B : A est parent d'un parent de B
        foreach ($b->getParents() as $parentOfB) {
            if ($a->getChildren()->contains($parentOfB)) {
                return true; 
            }
        }
        
        // B est Grand-Parent de A : B est parent d'un parent de A
        foreach ($a->getParents() as $parentOfA) {
            if ($b->getChildren()->contains($parentOfA)) {
                return true; 
            }
        }
        return false;
    }

    private function areSiblings(Person $a, Person $b): bool
    {
        if ($a->getId() === $b->getId()) {
            return false; // Évite l'auto-référence
        }
        
        // Partagent au moins un parent commun
        foreach ($a->getParents() as $aParent) {
            if ($b->getParents()->contains($aParent)) {
                return true; 
            }
        }
        return false;
    }

    private function isUncleAuntNephewNiece(Person $a, Person $b): bool
    {
        // Cas 1: A est Oncle/Tante de B
        foreach ($b->getParents() as $parentOfB) {
            // Le Donateur (A) est-il le frère/sœur du parent de B ?
            if ($this->areSiblings($a, $parentOfB)) {
                return true; 
            }
        }
        
        // Cas 2: B est Oncle/Tante de A (pour couvrir la symétrie de la parenté)
        foreach ($a->getParents() as $parentOfA) {
            // Le Bénéficiaire (B) est-il le frère/sœur du parent de A ?
            if ($this->areSiblings($b, $parentOfA)) {
                return true; 
            }
        }
        
        return false;
    }
}