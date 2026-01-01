<?php

namespace App\Form\Notary;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ZoneCoverageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('cities', PostalCodeAutocompleteField::class, [
            'label' => 'Vos codes postaux',
            'help' => 'Vous pouvez sélectionner jusqu\'à ' . $options['quota'] . ' codes postaux selon votre offre actuelle.',
            'multiple' => true,
            // Configuration TomSelect
            'tom_select_options' => [
                'maxItems' => $options['quota'],
                'plugins' => ['remove_button', 'clear_button'],
                'placeholder' => 'Tapez un code postal (ex: 44000)',
            ],
            'constraints' => [
                new Count(
                    max: $options['quota'],
                    maxMessage: 'Votre offre actuelle est limitée à {{ limit }} codes postaux.',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'quota' => 1,
            'data_class' => null,
            // On désactive la validation HTML5 pour laisser Symfony (et TomSelect) gérer proprement les messages
            'attr' => ['novalidate' => 'novalidate']
        ]);
        $resolver->setAllowedTypes('quota', 'int');
    }
}