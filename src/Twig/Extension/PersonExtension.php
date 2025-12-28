<?php

namespace App\Twig\Extension;

use App\Service\PersonService;
use App\Entity\Person;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PersonExtension extends AbstractExtension
{
    public function __construct(private PersonService $personService) {}

    public function getFilters(): array
    {
        return [
            // On crée un filtre |age et un filtre |relation_label
            new TwigFilter('age', [$this->personService, 'calculateAge']),
            new TwigFilter('relation_label', [$this, 'formatRelation']),
        ];
    }

    public function formatRelation(Person $person): string
    {
        // On récupère le code de la relation liée à la personne
        $code = $person->getRelationship()->getCode(); 
        return $this->personService->getGenderedLabel($code, $person->getGender());
    }
}