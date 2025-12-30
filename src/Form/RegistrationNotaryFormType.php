<?php

namespace App\Form;

use App\Entity\Notary;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

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
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse du siège',
            ])
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
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(['message' => 'Entrez un mot de passe']),
                    new Length(['min' => 8, 'minMessage' => '8 caractères minimum'])
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