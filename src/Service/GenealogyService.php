<?php

namespace App\Service;

use App\Entity\Person;

class GenealogyService
{
    private array $codes;

    /**
     * Injection des codes de relation depuis config/services.yaml (ex: R_PE, R_GPPE).
     */
    public function __construct(array $relationshipCodes)
    {
        $this->codes = $relationshipCodes;
    }

    /**
     * Détermine la clé de relation fiscale entre un Donateur et un Bénéficiaire.
     * * @param Person $donor La personne qui donne (A)
     * @param Person $beneficiary La personne qui reçoit (B)
     * @return string La clé de relation pour le TaxCatalog (ex: R_PE)
     */
    public function getRelationshipKey(Person $donor, Person $beneficiary): string
    {
        // 1. Ligne Directe de 1er degré (Parent/Enfant) - Abattement 100 000 €
        if ($this->isDirectParentOrChild($donor, $beneficiary)) {
            return $this->codes['PARENT_ENFANT']; // R_PE
        }

        // 2. Ligne Directe de 2e degré (Grand-Parent/Petit-Enfant) - Abattement 31 865 €
        if ($this->isGrandParentOrGrandChild($donor, $beneficiary)) {
            return $this->codes['GRAND_PARENT_PETIT_ENFANT']; // R_GPPE
        }

        // 3. Ligne Collatérale de 2e degré (Frère/Sœur) - Abattement 15 932 €
        if ($this->areSiblings($donor, $beneficiary)) {
            return $this->codes['FRERE_SOEUR']; // R_FS
        }
        
        // 4. Ligne Collatérale de 3e degré (Oncle/Tante - Neveu/Nièce) - Abattement 7 967 €
        if ($this->isUncleAuntNephewNiece($donor, $beneficiary)) {
            return $this->codes['ONCLE_NEVEU']; // R_ON
        }

        // 5. Cas par défaut : Tiers ou Parenté très éloignée
        return $this->codes['TIERS']; // R_T
    }

    // -------------------------------------------------------------------------
    // --- LOGIQUE PURE : Utilisation des collections getParents/getChildren ---
    // -------------------------------------------------------------------------

    /**
     * Vérifie si A est parent de B, ou si B est parent de A.
     */
    private function isDirectParentOrChild(Person $a, Person $b): bool
    {
        // A est parent de B, OU B est parent de A
        return $a->getChildren()->contains($b) || $b->getChildren()->contains($a);
    }
    
    /**
     * Vérifie si A est Grand-Parent de B, ou B est Grand-Parent de A.
     */
    private function isGrandParentOrGrandChild(Person $a, Person $b): bool
    {
        // Teste si A est un Grand-Parent de B
        foreach ($b->getParents() as $parentOfB) {
            // Est-ce que A est un parent de ce parent ?
            if ($a->getChildren()->contains($parentOfB)) {
                return true; 
            }
        }
        
        // Teste la symétrie : si B est un Grand-Parent de A
        foreach ($a->getParents() as $parentOfA) {
            // Est-ce que B est un parent de ce parent ?
            if ($b->getChildren()->contains($parentOfA)) {
                return true; 
            }
        }
        return false;
    }

    /**
     * Vérifie si A et B partagent au moins un parent (Frère/Sœur).
     */
    private function areSiblings(Person $a, Person $b): bool
    {
        if ($a->getId() === $b->getId()) {
            return false; // Évite l'auto-référence
        }
        
        // Ils doivent partager au moins un parent commun
        foreach ($a->getParents() as $aParent) {
            if ($b->getParents()->contains($aParent)) {
                return true; 
            }
        }
        return false;
    }

    /**
     * Vérifie la relation Oncle/Tante ou Neveu/Nièce (3e degré).
     *
     * La relation fiscale est symétrique pour cet abattement, nous vérifions donc
     * uniquement la relation A est Oncle/Tante de B.
     */
    private function isUncleAuntNephewNiece(Person $a, Person $b): bool
    {
        // Cas 1: A est Oncle/Tante de B (Bénéficiaire)
        foreach ($b->getParents() as $parentOfB) {
            // Le Donateur (A) est-il le frère/sœur du parent de B ?
            if ($this->areSiblings($a, $parentOfB)) {
                return true; 
            }
        }
        
        // Cas 2: B est Oncle/Tante de A (Symétrie pour la relation "parenté")
        foreach ($a->getParents() as $parentOfA) {
            // Le Bénéficiaire (B) est-il le frère/sœur du parent de A ?
            if ($this->areSiblings($b, $parentOfA)) {
                return true; 
            }
        }
        
        return false;
    }
}