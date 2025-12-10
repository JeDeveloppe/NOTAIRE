<?php

namespace App\Service;

use App\Entity\Person;
use Doctrine\Common\Collections\Collection; // Ajout de l'import Collection

class GenealogyService
{
    private array $codes;

    /**
     * @param array $relationshipCodes Le tableau de codes injecté depuis config/services.yaml
     */
    public function __construct(array $relationshipCodes)
    {
        $this->codes = $relationshipCodes;
    }

    /**
     * Détermine la clé de relation fiscale entre un Donateur (A) et un Bénéficiaire (B).
     */
    public function getRelationshipKey(Person $donor, Person $beneficiary): string
    {
        // Évite l'auto-référence et garantit qu'on ne cherche pas de lien si c'est la même personne
        if ($donor->getId() === $beneficiary->getId()) {
            return $this->codes['TIERS']; // Ne devrait pas arriver dans la simulation, mais plus sûr.
        }

        // 1. Ligne Directe de 1er degré (Parent/Enfant)
        if ($this->isDirectParentOrChild($donor, $beneficiary)) {
            return $this->codes['PARENT_ENFANT']; // R_PE
        }

        // 2. Ligne Directe de 2e degré (Grand-Parent/Petit-Enfant)
        if ($this->isGrandParentOrGrandChild($donor, $beneficiary)) {
            return $this->codes['GRAND_PARENT_PETIT_ENFANT']; // R_GPPE
        }

        // 3. Ligne Collatérale de 2e degré (Frère/Sœur)
        if ($this->areSiblings($donor, $beneficiary)) {
            return $this->codes['FRERE_SOEUR']; // R_FS
        }
        
        // 4. Ligne Collatérale de 3e degré (Oncle/Tante - Neveu/Nièce)
        if ($this->isUncleAuntNephewNiece($donor, $beneficiary)) {
            return $this->codes['ONCLE_NEVEU']; // R_ON
        }

        // 5. Cas par défaut : Tiers ou Parenté très éloignée
        return $this->codes['TIERS']; // R_T
    }

    // -------------------------------------------------------------------------
    // --- LOGIQUE PURE (méthodes privées basées sur Person.parents/children) ---
    // -------------------------------------------------------------------------

    private function isDirectParentOrChild(Person $a, Person $b): bool
    {
        // Teste si A est parent de B, OU si B est parent de A
        return $a->getChildren()->contains($b) || $b->getChildren()->contains($a);
    }
    
    /**
     * Vérifie si A est Grand-Parent de B OU si B est Grand-Parent de A.
     */
    private function isGrandParentOrGrandChild(Person $a, Person $b): bool
    {
        // A est parent de B ?
        $aIsParentOfB = $this->isParentOf($a, $b);
        // B est parent de A ?
        $bIsParentOfA = $this->isParentOf($b, $a);

        // Si la relation est de 1er degré, ce n'est pas un grand-parent (déjà traité par isDirectParentOrChild)
        if ($aIsParentOfB || $bIsParentOfA) {
            return false;
        }

        // Vérification de la relation de 2e degré
        return $this->isGrandParentOf($a, $b) || $this->isGrandParentOf($b, $a);
    }

    /**
     * Retourne vrai si A et B partagent au moins un parent commun.
     */
    private function areSiblings(Person $a, Person $b): bool
    {
        if ($a->getId() === $b->getId()) {
            return false; 
        }

        // Récupère les IDs des parents pour une intersection rapide
        $aParentsIds = $a->getParents()->map(fn(Person $p) => $p->getId())->toArray();
        $bParentsIds = $b->getParents()->map(fn(Person $p) => $p->getId())->toArray();
        
        return !empty(array_intersect($aParentsIds, $bParentsIds));
    }

    /**
     * Retourne vrai si l'un est Oncle/Tante de l'autre.
     */
    private function isUncleAuntNephewNiece(Person $a, Person $b): bool
    {
        // Cas 1: A est frère/sœur du parent de B
        if ($this->isSiblingOfParent($a, $b)) {
            return true; 
        }
        
        // Cas 2: B est frère/sœur du parent de A (couvre la symétrie)
        if ($this->isSiblingOfParent($b, $a)) {
            return true; 
        }
        
        return false;
    }
    
    // -------------------------------------------------------------------------
    // --- FONCTIONS D'AIDE UTILITAIRES ---
    // -------------------------------------------------------------------------

    /**
     * Vérifie si A est parent direct de B.
     */
    private function isParentOf(Person $a, Person $b): bool
    {
        return $a->getChildren()->contains($b);
    }

    /**
     * Vérifie si A est grand-parent de B.
     */
    private function isGrandParentOf(Person $a, Person $b): bool
    {
        foreach ($b->getParents() as $parentOfB) {
            if ($a->getChildren()->contains($parentOfB)) {
                return true; 
            }
        }
        return false;
    }
    
    /**
     * Vérifie si A est le frère/sœur d'un parent de B.
     */
    private function isSiblingOfParent(Person $a, Person $b): bool
    {
        foreach ($b->getParents() as $parentOfB) {
            if ($this->areSiblings($a, $parentOfB)) {
                return true; 
            }
        }
        return false;
    }
}