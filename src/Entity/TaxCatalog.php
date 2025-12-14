<?php

namespace App\Entity;

use App\Repository\TaxCatalogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxCatalogRepository::class)]
class TaxCatalog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $taxRateLower = null;

    #[ORM\Column]
    private ?int $taxRateUpper = null;

    #[ORM\Column(length: 255)]
    private ?string $relationshipBetweenThe2People = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxRateLower(): ?int
    {
        return $this->taxRateLower;
    }

    public function setTaxRateLower(int $taxRateLower): static
    {
        $this->taxRateLower = $taxRateLower;

        return $this;
    }

    public function getTaxRateUpper(): ?int
    {
        return $this->taxRateUpper;
    }

    public function setTaxRateUpper(int $taxRateUpper): static
    {
        $this->taxRateUpper = $taxRateUpper;

        return $this;
    }

    public function getRelationshipBetweenThe2People(): ?string
    {
        return $this->relationshipBetweenThe2People;
    }

    public function setRelationshipBetweenThe2People(string $relationshipBetweenThe2People): static
    {
        $this->relationshipBetweenThe2People = $relationshipBetweenThe2People;

        return $this;
    }
}
