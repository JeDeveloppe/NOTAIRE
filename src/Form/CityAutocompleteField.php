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
            'placeholder' => 'Cherchez une ville (ex: Nantes ou 44)',
            'choice_label' => function (City $city) {
                return $city->getName() . ' (' . $city->getDepartmentCode() . ')';
            },
            // On définit comment on cherche dans la base
            'searchable_fields' => ['name', 'departmentCode', 'inseeCode'],
            'max_results' => 10,
            'attr' => [
                'class' => 'form-control border-0 bg-light rounded-4 px-4',
            ],
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}