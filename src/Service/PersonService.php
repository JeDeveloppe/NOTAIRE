<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Entity\Person;

class PersonService
{
    public function __construct(
        private ParameterBagInterface $params
    ) {}

    /**
     * Retourne le libellé genré (ex: Fils, Nièce, etc.)
     */
    public function getGenderedLabel(string $relationshipCode, string $gender): string
    {
        $labels = $this->params->get('app.gender_labels');

        // On cherche dans le tableau du yaml, sinon on renvoie le code brut
        return $labels[$relationshipCode][$gender] ?? $labels[$relationshipCode]['O'] ?? $relationshipCode;
    }

    /**
     * Calcule l'âge d'une personne à une date précise.
     */
    public function calculateAge(Person $person, ?\DateTimeInterface $atDate = null): int
    {
        $birthDate = $person->getBirthdate();
        if (!$birthDate) {
            return 0;
        }

        // PRIORITÉ 1 : La date transmise par l'utilisateur
        // PRIORITÉ 2 : Sinon, si décédé, sa date de décès
        // PRIORITÉ 3 : Sinon, aujourd'hui
        $referenceDate = $atDate ?? $person->getDeathDate() ?? new \DateTime();

        // SÉCURITÉ NOTARIALE : 
        // Si on demande un calcul à une date ($atDate) qui est POSTÉRIEURE au décès,
        // l'âge doit rester figé au moment du décès. 
        if ($person->getDeathDate() && $referenceDate > $person->getDeathDate()) {
            $referenceDate = $person->getDeathDate();
        }

        // SÉCURITÉ : Si la date de référence est antérieure à la naissance
        if ($referenceDate < $birthDate) {
            return 0;
        }

        return $birthDate->diff($referenceDate)->y;
    }

    /**
     * Vérifie si la personne est en vie
     */
    public function isAlive(Person $person): bool
    {
        return $person->getDeathDate() === null;
    }
}
