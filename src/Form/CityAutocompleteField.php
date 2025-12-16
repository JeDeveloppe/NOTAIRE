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
            'placeholder' => 'Commencez à taper votre ville ou code postal...',
            
            // ⭐️ Affichage du résultat ⭐️
            'choice_label' => fn (City $city) => $city->getName() . ' (' . $city->getPostalCode() . ')',

            // ⭐️ Champs sur lesquels la recherche doit s'effectuer (Optimisation BDD) ⭐️
            'searchable_fields' => ['name', 'postalCode'],
            
            // ⭐️ Minimum de caractères avant de lancer la requête (Meilleure performance) ⭐️
            'min_characters' => 3, 
            'attribute' => [
                'class' => 'form-control',
            ],
        ]);
    }

    public function getParent(): string
    {
        // On retourne la classe de base pour l'autocomplétion
        return BaseEntityAutocompleteType::class;
    }
}