<?php

namespace App\Form;

use App\Entity\Donation;
use App\Entity\Person;
use App\Repository\DonationRuleRepository;
use App\Repository\PersonRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DonationType extends AbstractType
{
    public function __construct(
        private DonationRuleRepository $ruleRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];

        // Récupération dynamique des règles fiscales
        $rules = $this->ruleRepository->findAll();
        $typeChoices = [];
        foreach ($rules as $rule) {
            $typeChoices[$rule->getLabel()] = $rule->getTaxSystem();
        }

        $builder
            ->add('donor', EntityType::class, [
                'class' => Person::class,
                'label' => 'Donateur',
                'placeholder' => 'Choisir le donateur...', // Champ vide au départ
                'required' => true,
                'choice_label' => fn(Person $p) => $p->getFirstname() . ' ' . strtoupper($p->getLastname()),
                'query_builder' => function (PersonRepository $er) use ($user) {
                    return $er->createQueryBuilder('p')
                        ->where('p.owner = :user')
                        ->setParameter('user', $user)
                        ->orderBy('p.firstname', 'ASC');
                },
            ])
            ->add('beneficiary', EntityType::class, [
                'class' => Person::class,
                'label' => 'Bénéficiaire',
                'placeholder' => 'Choisir le bénéficiaire...', // Champ vide au départ
                'required' => true,
                'choice_label' => fn(Person $p) => $p->getFirstname() . ' ' . strtoupper($p->getLastname()),
                'query_builder' => function (PersonRepository $er) use ($user) {
                    return $er->createQueryBuilder('p')
                        ->where('p.owner = :user')
                        ->setParameter('user', $user)
                        ->orderBy('p.firstname', 'ASC');
                },
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant (€)',
                'attr' => ['placeholder' => 'Ex: 31865']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Nature fiscale du don',
                'choices' => $typeChoices,
                'placeholder' => 'Sélectionnez la règle fiscale...', // Champ vide au départ
                'help' => 'Sélectionnez la règle fiscale à appliquer pour ce don.'
            ])
            ->add('createdAt', DateType::class, [
                'label' => 'Date du don',
                'widget' => 'single_text',
                'data' => new \DateTime(), // Date du jour par défaut
            ])
            ->add('taxPaid', NumberType::class, [
                'label' => 'Impôts payés pour cet acte (€)',
                'required' => true,
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'placeholder' => '0',
                    'min' => 0,
                    'step' => '0.01'
                ],
                'help' => 'Indiquez 0 si l\'acte était totalement exonéré.'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Donation::class,
            'user' => null,
        ]);
    }
}