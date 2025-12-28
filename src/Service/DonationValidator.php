<?php

namespace App\Service;

use App\Entity\Donation;
use App\Repository\DonationRuleRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;

class DonationValidator
{
    public function __construct(
        private DonationRuleRepository $ruleRepository,
        private DonationService $donationService // On injecte le service pour le lien de parenté
    ) {}

    public function validate(Donation $donation, FormInterface $form): bool
    {
        $donor = $donation->getDonor();
        $beneficiary = $donation->getBeneficiary();

        // 1. Identité
        if ($donor === $beneficiary) {
            $form->addError(new FormError("Une personne ne peut pas se faire une donation à elle-même."));
        }

        // 2. Identification de la règle
        $relCode = $this->donationService->determineRelationshipCode($donor, $beneficiary);
        $rule = $this->ruleRepository->findOneBy([
            'relationshipCode' => $relCode,
            'taxSystem' => $donation->getType()
        ]);

        if (!$rule) {
            $form->addError(new FormError("Le lien de parenté détecté ne permet pas ce type de donation fiscale."));
            return false;
        }

        // 3. Calcul et validation des âges
        $ageDonor = $donor->getBirthDate()->diff($donation->getCreatedAt())->y;
        $ageReceiver = $beneficiary->getBirthDate()->diff($donation->getCreatedAt())->y;

        if ($ageDonor >= $rule->getDonorMaxAge()) {
            $form->get('donor')->addError(new FormError(
                "Âge limite dépassé : Le donateur doit avoir moins de " . $rule->getDonorMaxAge() . " ans."
            ));
        }

        if ($ageReceiver < $rule->getReceiverMinAge()) {
            $form->get('beneficiary')->addError(new FormError(
                "Condition d'âge non remplie : Le bénéficiaire doit avoir au moins " . $rule->getReceiverMinAge() . " ans."
            ));
        }

        // 4. Vérification de cohérence de l'impôt
        if ($donation->getTaxPaid() > $donation->getAmount()) {
            $form->get('taxPaid')->addError(new FormError(
                "Le montant de l'impôt ne peut pas être supérieur au montant du don lui-même."
            ));
        }

        return $form->isValid();
    }
}
