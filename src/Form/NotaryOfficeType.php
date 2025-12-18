<?php

namespace App\Form;

use App\Entity\NotaryOffice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class NotaryOfficeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de l\'étude'])
            ->add('phone', TextType::class, ['label' => 'Téléphone'])
            ->add('streetNumber', TextType::class, ['label' => 'N°', 'required' => false])
            ->add('subdivisionIndicator', TextType::class, ['label' => 'Indice', 'required' => false])
            ->add('street', TextType::class, ['label' => 'Rue'])
            ->add('radius', IntegerType::class, [
                'required' => false,
                // On peut ajouter des attributs HTML5 ici si on veut
                'attr' => [
                    'min' => 5,
                    'max' => 200
                ]
            ])
            
            // Ville du siège (principale)
            ->add('city', CityAutocompleteField::class, [
                'label' => 'Ville du siège',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NotaryOffice::class]);
    }
}