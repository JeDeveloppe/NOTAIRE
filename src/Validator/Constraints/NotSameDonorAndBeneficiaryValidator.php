<?php

namespace App\Validator\Constraints;

use App\Entity\Act;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NotSameDonorAndBeneficiaryValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotSameDonorAndBeneficiary) {
            throw new UnexpectedTypeException($constraint, NotSameDonorAndBeneficiary::class);
        }

        // Vérifie que nous validons bien une instance de l'entité Act
        if (!$value instanceof Act) {
            throw new UnexpectedTypeException($value, Act::class);
        }

        // Récupération des objets Donateur et Bénéficiaire
        $donor = $value->getDonor();
        $beneficiary = $value->getBeneficiary();

        // Si les deux sont définis et qu'ils sont la même personne (même objet/ID)
        if ($donor && $beneficiary && $donor === $beneficiary) {
            
            // Ajoute l'erreur à la contrainte de la classe (l'entité Act)
            $this->context->buildViolation($constraint->message)
                ->atPath('beneficiary') // Affiche l'erreur sous le champ "Bénéficiaire"
                ->addViolation();
        }
    }
}