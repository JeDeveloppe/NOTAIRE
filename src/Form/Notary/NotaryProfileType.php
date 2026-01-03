<?php

namespace App\Form\Notary;

use App\Entity\Notary;
use App\Form\CityAutocompleteField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NotaryProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'étude',
                'required' => true, // Active l'attribut HTML required
                'constraints' => [
                    new NotBlank(message: 'Le nom de l\'étude est obligatoire'),
                ],
                'attr' => ['class' => 'form-control rounded-4']
            ])
            ->add('siret', TextType::class, [
                'label' => 'Numéro SIRET',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le SIRET est requis'),
                    new Length(
                        min: 14, 
                        max: 14, 
                        exactMessage: 'Le SIRET doit faire exactement {{ limit }} chiffres'
                    ),
                    new Regex(
                        pattern: '/^[0-9]+$/',
                        message: 'Le SIRET ne doit contenir que des chiffres'
                    )
                ],
                'attr' => ['class' => 'form-control rounded-4', 'maxlength' => 14]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone direct',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le téléphone est obligatoire'),
                ],
                'attr' => ['class' => 'form-control rounded-4']
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse de l\'étude',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'L\'adresse est obligatoire'),
                ],
                'attr' => ['class' => 'form-control rounded-4']
            ])
            ->add('city', CityAutocompleteField::class, [
                'label' => 'Ville de l\'étude',
                'required' => true,
                // L'autocomplete gère souvent sa propre validation interne
            ])
            ->add('website', UrlType::class, [
                'label' => 'Site Internet',
                'required' => false, // Souvent optionnel pour un notaire
                'attr' => ['class' => 'form-control rounded-4']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notary::class,
        ]);
    }
}