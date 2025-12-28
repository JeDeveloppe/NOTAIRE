<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Person;
use App\Entity\Donation;
use App\Repository\DonationRuleRepository;

class DonationService
{
    public function __construct(
        private DonationRuleRepository $ruleRepository,
        private PersonService $personService,
        private array $taxBrackets
    ) {}

    /**
     * BILAN COMPLET : Scanne tout l'arbre pour les simulations et l'historique
     */
    public function getFullPatrimonialBilan(Person $person): array
    {
        // 1. Simulations de RÉCEPTION (De qui je peux recevoir ?)
        $received = [];
        $potentialDonors = array_merge(
            $person->getParents()->toArray(),
            $this->getGrandParents($person),
            $this->getGreatGrandParents($person),
            $person->getUnclesAndAunts() // Méthode déjà présente dans ton entité Person
        );

        foreach ($potentialDonors as $donor) {
            $received[] = $this->simulateMaxTaxFree($donor, $person);
        }

        // 2. Simulations de TRANSMISSION (À qui je peux donner ?)
        $given = [];
        $targets = array_merge(
            $person->getChildren()->toArray(),
            $this->getGrandChildren($person),
            $this->getGreatGrandChildren($person),
            $person->getSiblings(),
            $person->getNephewsAndNieces()
        );

        foreach ($targets as $target) {
            $given[] = $this->simulateMaxTaxFree($person, $target);
        }

        // 3. Registre Historique Réel (Dons déjà enregistrés)
        $historyReceived = $person->getDonationsReceived()->toArray();
        $historyGiven = $person->getDonationsGiven()->toArray();

        $sorter = fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt();
        usort($historyReceived, $sorter);
        usort($historyGiven, $sorter);

        return [
            'person' => $person,
            'receivedSimulations' => $received,
            'givenSimulations' => $given,
            'history_received' => $historyReceived,
            'history_given' => $historyGiven,
            'totalGlobal' => array_sum(array_column($given, 'total_allowance'))
        ];
    }

    /**
     * Calcule la capacité restante pour un binôme précis
     */
    public function simulateMaxTaxFree(Person $donor, Person $beneficiary, ?\DateTimeInterface $referenceDate = null): array
    {
        // On définit la date de référence (aujourd'hui par défaut)
        $referenceDate = $referenceDate ?? new \DateTimeImmutable();

        if (!$this->personService->isAlive($donor) || !$this->personService->isAlive($beneficiary)) {
            return $this->formatEmptyResult($donor, $beneficiary);
        }

        // On passe la date pour calculer ce qui était consommé À CETTE DATE PRÉCISE
        $consumedAmounts = $this->getConsumedAmountsByTaxSystem($donor, $beneficiary, $referenceDate);
        $relCode = $this->determineRelationshipCode($donor, $beneficiary);

        $rules = $this->ruleRepository->findByRelationshipCode($relCode);

        // On passe la date pour calculer l'âge qu'ils auront À CETTE DATE PRÉCISE
        $rulesAnalysis = $this->buildRulesAnalysis($donor, $beneficiary, $rules, $consumedAmounts, $referenceDate);

        $totalAvailable = 0;
        foreach ($rulesAnalysis as $rule) {
            if ($rule['is_valid']) {
                $totalAvailable += $rule['available'];
            }
        }

        return [
            'donor' => $donor,
            'beneficiary' => $beneficiary,
            'relationship_code' => $relCode,
            'rules' => $rulesAnalysis,
            'total_allowance' => $totalAvailable,
            'simulation_date' => $referenceDate
        ];
    }

    private function buildRulesAnalysis(Person $donor, Person $beneficiary, array $rules, array $consumedAmounts, \DateTimeInterface $referenceDate): array
    {
        $analysis = [];

        // On utilise la date de simulation pour calculer l'âge futur
        $donorAge = $this->personService->calculateAge($donor, $referenceDate);
        $beneficiaryAge = $this->personService->calculateAge($beneficiary, $referenceDate);

        foreach ($rules as $rule) {
            // Validation des conditions d'âge à la date de simulation
            $ageOk = ($donorAge < $rule->getDonorMaxAge()) && ($beneficiaryAge >= $rule->getReceiverMinAge());
            $taxSystem = strtolower($rule->getTaxSystem() ?? 'classic');

            $alreadyGiven = str_contains($taxSystem, 'sarkozy') ? $consumedAmounts['sarkozy'] : $consumedAmounts['classic'];
            $available = $ageOk ? max(0, $rule->getAllowanceAmount() - $alreadyGiven) : 0;

            $reason = "";
            if (!$ageOk) {
                if ($donorAge >= $rule->getDonorMaxAge()) $reason = "Âge limite atteint au " . $referenceDate->format('d/m/Y') . " (> " . $rule->getDonorMaxAge() . " ans)";
                if ($beneficiaryAge < $rule->getReceiverMinAge()) $reason = "Bénéficiaire trop jeune au " . $referenceDate->format('d/m/Y') . " (< " . $rule->getReceiverMinAge() . " ans)";
            }

            $analysis[] = [
                'label' => $rule->getLabel(),
                'max_allowance' => (float)$rule->getAllowanceAmount(),
                'consumed' => (float)$alreadyGiven,
                'available' => (float)$available,
                'is_valid' => $ageOk,
                'tax_system' => $taxSystem,
                'reason' => $reason
            ];
        }
        return $analysis;
    }

    private function getConsumedAmountsByTaxSystem(Person $donor, Person $beneficiary, \DateTimeInterface $referenceDate): array
    {
        // Le rappel fiscal est de 15 ans AVANT la date de simulation
        $limitDate = \DateTimeImmutable::createFromInterface($referenceDate)->modify('-15 years');
        $totals = ['classic' => 0.0, 'sarkozy' => 0.0];

        foreach ($beneficiary->getDonationsReceived() as $past) {
            // Un don compte s'il est :
            // 1. Entre le même donateur et bénéficiaire
            // 2. Effectué AVANT la date de simulation
            // 3. Effectué APRÈS la date limite (rappel fiscal des 15 ans)
            if (
                $past->getDonor() === $donor
                && $past->getCreatedAt() <= $referenceDate
                && $past->getCreatedAt() >= $limitDate
            ) {

                $type = strtolower($past->getType() ?? 'classic');
                if (str_contains($type, 'sarkozy')) {
                    $totals['sarkozy'] += (float)$past->getAmount();
                } else {
                    $totals['classic'] += (float)$past->getAmount();
                }
            }
        }
     
        return $totals;
    }

    /**
     * Logique simplifiée de détection des liens de parenté fiscaux
     */
    public function determineRelationshipCode(Person $donor, Person $beneficiary): string
    {
        // --- 1. LIGNE DESCENDANTE (Robert donne à Marc) ---
        if ($donor->getChildren()->contains($beneficiary)) return 'ENFANT';

        // Si Robert (donor) a Marc (beneficiary) dans ses petits-enfants
        if (in_array($beneficiary, $this->getGrandChildren($donor), true)) return 'PETIT_ENFANT';

        if (in_array($beneficiary, $this->getGreatGrandChildren($donor), true)) return 'ARRIERE_PETIT_ENFANT';

        // --- 2. LIGNE ASCENDANTE (Marc donne à Robert) ---
        if ($donor->getParents()->contains($beneficiary)) return 'PARENT';

        // Si Marc (donor) a Robert (beneficiary) dans ses grands-parents
        if (in_array($beneficiary, $this->getGrandParents($donor), true)) return 'GRAND_PARENT';

        // --- 3. COLLATÉRAUX ---
        foreach ($donor->getParents() as $parent) {
            if ($beneficiary->getParents()->contains($parent)) return 'FRERE_SOEUR';
        }

        if (in_array($beneficiary, $donor->getNephewsAndNieces(), true)) return 'NEVEU_NIECE';
        if (in_array($donor, $beneficiary->getNephewsAndNieces(), true)) return 'ONCLE_TANTE';

        return 'TIERS';
    }

    // --- HELPERS DE NAVIGATION GÉNÉALOGIQUE ---

    private function getGrandParents(Person $person): array
    {
        $gps = [];
        foreach ($person->getParents() as $parent) {
            foreach ($parent->getParents() as $gp) {
                $gps[] = $gp;
            }
        }
        return $gps;
    }

    private function getGreatGrandParents(Person $person): array
    {
        $ggps = [];
        foreach ($this->getGrandParents($person) as $gp) {
            foreach ($gp->getParents() as $ggp) {
                $ggps[] = $ggp;
            }
        }
        return $ggps;
    }

    private function getGrandChildren(Person $person): array
    {
        $gcs = [];
        foreach ($person->getChildren() as $child) {
            foreach ($child->getChildren() as $gc) {
                $gcs[] = $gc;
            }
        }
        return $gcs;
    }

    private function getGreatGrandChildren(Person $person): array
    {
        $ggcs = [];
        foreach ($this->getGrandChildren($person) as $gc) {
            foreach ($gc->getChildren() as $ggc) {
                $ggcs[] = $ggc;
            }
        }
        return $ggcs;
    }

    private function formatEmptyResult(Person $donor, Person $beneficiary): array
    {
        return ['donor' => $donor, 'beneficiary' => $beneficiary, 'relationship_code' => 'N/A', 'rules' => [], 'total_allowance' => 0];
    }

    /**
     * Calcule les statistiques globales pour le tableau de bord de l'utilisateur
     */
    /**
     * Calcule les statistiques détaillées pour le dashboard familial
     */
    public function getUserDashboardStats(User $user): array
    {
        $persons = $user->getPeople();
        $limitDate = new \DateTimeImmutable('-15 years');

        $activeDonations = [];
        $expiredDonations = [];

        foreach ($persons as $person) {
            // On récupère les dons donnés par chaque membre de la famille
            foreach ($person->getDonationsGiven() as $donation) {
                if ($donation->getCreatedAt() >= $limitDate) {
                    $activeDonations[] = $donation;
                } else {
                    $expiredDonations[] = $donation;
                }
            }
        }

        // Tri par date décroissante pour l'affichage
        $sorter = fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt();
        usort($activeDonations, $sorter);
        usort($expiredDonations, $sorter);

        return [
            'totalMembers' => count($persons),
            'activeDonations' => $activeDonations,
            'expiredDonations' => $expiredDonations,
        ];
    }

    /**
     * Récupère tous les dons effectués entre un donateur et un bénéficiaire précis.
     */
    public function getDonationsBetween(Person $donor, Person $beneficiary): array
    {
        $results = [];
        foreach ($donor->getDonationsGiven() as $donation) {
            if ($donation->getBeneficiary() === $beneficiary) {
                $results[] = $donation;
            }
        }
        return $results;
    }
}
