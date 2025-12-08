<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotSameDonorAndBeneficiary extends Constraint
{
    // Ce message sera affiché dans EasyAdmin si la validation échoue.
    public string $message = 'Le bénéficiaire ne peut pas être le donateur.';

    /**
     * Cette contrainte doit être appliquée au niveau de la classe (entité) entière.
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}