<?php

namespace App\Form;

use App\Entity\City;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class CityAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => City::class,
            'placeholder' => 'Rechercher par ville ou code postal (ex: 75001)...',
            'choice_label' => fn (City $city) => sprintf('%s (%s)', $city->getName(), $city->getPostalCode()),
            
            // On force la recherche sur les deux colonnes
            'searchable_fields' => ['name', 'postalCode'],
            
            // On expose l'objet City complet pour le JS plus tard
            'expose_fields' => ['name', 'postalCode'],
        ]);
    }

    public function getParent(): string
    {
        // On retourne la classe de base pour l'autocomplétion
        return BaseEntityAutocompleteType::class;
    }
}