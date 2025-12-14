<?php
// src/Form/NotaireCodeConsultationType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType; 
use Symfony\Component\Validator\Constraints\Length;      
use Symfony\Component\Validator\Constraints\NotBlank;    
use Symfony\Component\Validator\Constraints\Regex; 

class NotaireCodeConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('uniqueCode', TextType::class, [
                'label' => 'Code de Consultation',
                
                'constraints' => [
                    // Utilisation de l'argument nommé pour éviter les erreurs de version
                    new NotBlank(message: 'Veuillez saisir le code de consultation.'),
                    
                    // Utilisation des arguments nommés pour Length et Regex
                    new Length(
                        min : 9, 
                        max : 9, 
                        exactMessage : 'Le code doit contenir exactement 9 caractères (Ex: ABCD-1234).',
                    ),
                    new Regex(
                        pattern: '/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', 
                        message: 'Le format du code est invalide. Veuillez utiliser le format "XXXX-XXXX" (lettres majuscules ou chiffres).',
                    ),
                ],

                'attr' => [
                    'class' => 'form-control form-control-lg text-center',
                    'placeholder' => 'Ex: ABCD-1234',
                    'maxlength' => 9, 
                    'pattern' => '[A-Z0-9]{4}-[A-Z0-9]{4}', 
                ],

                'mapped' => false, 
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Consulter la Simulation',
                'attr' => ['class' => 'btn btn-success btn-lg']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, 
            'method' => 'POST', 
        ]);
    }
    
    public function getBlockPrefix(): string
    {
        return 'notaire_code_consultation';
    }
}