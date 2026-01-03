<?php

namespace App\Form;

use App\Entity\Notary;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class RegistrationNotaryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // --- SECTION ÉTUDE ---
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'étude',
                'attr' => ['placeholder' => 'ex: SCP Dupont & Associés']
            ])
            ->add('siret', TextType::class, [
                'label' => 'Numéro SIRET',
                'attr' => ['placeholder' => '14 chiffres']
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone professionnel',
                'attr' => [
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => '00.00.00.00.00'
                ],
                'constraints' => [
                    new Regex(
                        pattern: '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
                        message:'Le numéro de téléphone n\'est pas valide.'
                    )
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse du siège',
            ])
            ->add('city', CityAutocompleteField::class)
            ->add('website', UrlType::class, [
                'label' => 'Site internet (Optionnel)',
                'required' => false,
            ])

            // --- SECTION COMPTE UTILISATEUR (Imbriqué manuellement) ---
            // On ajoute les champs de l'User directement ici pour ne pas avoir deux formulaires
            ->add('email', EmailType::class, [
                'label' => 'Email de connexion',
                'mapped' => false, // Important : ce champ n'est pas dans l'entité Notary
                'constraints' => [new NotBlank(), new \Symfony\Component\Validator\Constraints\Email()]
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => 'Mot de passe'
                ],
                'label' => 'Mot de passe',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer un mot de passe'),
                    new Length(
                        min: 6,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères',
                        max: 4096,
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => false, // Désactive le label automatique pour éviter le doublon
                'attr' => ['class' => 'form-check-input me-2'],
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter nos conditions pour continuer.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notary::class,
        ]);
    }
}
