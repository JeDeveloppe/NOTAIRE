<?php

namespace App\Service;

use App\Entity\Person;
use DateTimeImmutable;

class PersonService
{
    // L'injection de dépendances (par exemple, le repository de Person) sera faite ici si besoin,
    // mais pour le calcul de l'âge seul, l'objet Person en paramètre suffit.

    /**
     * Calcule l'âge d'une personne à une date donnée.
     * C'est essentiel pour déterminer la valeur de l'usufruit ou les règles AV.
     */
    public function calculateAgeAtDate(Person $person, DateTimeImmutable $referenceDate): int
    {
        $birthDate = $person->getDateOfBirth();
        if ($birthDate === null) {
            // Gérer l'erreur ou retourner une valeur par défaut
            throw new \InvalidArgumentException("La date de naissance est manquante.");
        }

        // Calcule la différence entre la date de référence et la date de naissance
        $interval = $birthDate->diff($referenceDate);
        
        // Retourne la différence en années
        return $interval->y;
    }

    /**
     * Calcule l'âge actuel d'une personne.
     */
    public function calculateCurrentAge(Person $person): int
    {
        // Utilise la fonction principale avec la date du jour comme référence
        return $this->calculateAgeAtDate($person, new DateTimeImmutable());
    }

    /**
     * Détermine si une personne avait plus ou moins de 70 ans à une date clé (ex: versement AV).
     */
    public function wasOverSeventyAtDate(Person $person, DateTimeImmutable $keyDate): bool
    {
        return $this->calculateAgeAtDate($person, $keyDate) >= 70;
    }
}