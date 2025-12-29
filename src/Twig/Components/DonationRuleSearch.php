<?php

namespace App\Twig\Components;

use App\Repository\DonationRuleRepository;
use App\Repository\RelationshipRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class DonationRuleSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public ?int $relationshipId = null;

    public function __construct(
        private DonationRuleRepository $ruleRepository,
        private RelationshipRepository $relationshipRepository
    ) {}

    /**
     * Cette méthode est appelée par Twig via "this.rules"
     */
    public function getRules(): array
    {
        if (empty($this->query) && null === $this->relationshipId) {
            return $this->ruleRepository->findAll();
        }

        // Appel d'une méthode personnalisée dans ton Repository
        return $this->ruleRepository->findBySearchQuery(
            $this->query, 
            $this->relationshipId
        );
    }

    /**
     * Cette méthode est appelée par Twig via "this.relationships"
     */
    public function getRelationships(): array
    {
        return $this->relationshipRepository->findAll();
    }
}