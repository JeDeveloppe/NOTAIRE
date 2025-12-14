<?php

namespace App\Entity;

use App\Repository\FiscalAbatementRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FiscalAbatementRuleRepository::class)]
class FiscalAbatementRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Ajout de unique: true
    #[ORM\Column(length: 255, unique: true)]
    private ?string $code = null;

    // Ajout d'une description lisible (optionnel mais recommandé)
    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $typeOfLink = null;

    #[ORM\Column(length: 255)]
    private ?string $typeOfAct = null;

    #[ORM\Column]
    private ?int $amountInCents = null;

    #[ORM\Column]
    private ?int $cycleOfYear = null;

    // Renommage pour cohérence (anciennement ageMinToBeDonataire)
    #[ORM\Column]
    private ?int $minBeneficiaryAge = null;

    // Renommage pour cohérence (anciennement ageMaxToBeDonor)
    #[ORM\Column]
    private ?int $maxDonorAge = null;


    // --- Getters et Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
    
    public function getTypeOfLink(): ?string
    {
        return $this->typeOfLink;
    }

    public function setTypeOfLink(string $typeOfLink): static
    {
        $this->typeOfLink = $typeOfLink;

        return $this;
    }

    public function getTypeOfAct(): ?string
    {
        return $this->typeOfAct;
    }

    public function setTypeOfAct(string $typeOfAct): static
    {
        $this->typeOfAct = $typeOfAct;

        return $this;
    }

    public function getAmountInCents(): ?int
    {
        return $this->amountInCents;
    }

    public function setAmountInCents(int $amountInCents): static
    {
        $this->amountInCents = $amountInCents;

        return $this;
    }

    public function getCycleOfYear(): ?int
    {
        return $this->cycleOfYear;
    }

    public function setCycleOfYear(int $cycleOfYear): static
    {
        $this->cycleOfYear = $cycleOfYear;

        return $this;
    }

    // Renommage du Getter/Setter (minBeneficiaryAge)
    public function getMinBeneficiaryAge(): ?int
    {
        return $this->minBeneficiaryAge;
    }

    public function setMinBeneficiaryAge(int $minBeneficiaryAge): static
    {
        $this->minBeneficiaryAge = $minBeneficiaryAge;

        return $this;
    }

    // Renommage du Getter/Setter (maxDonorAge)
    public function getMaxDonorAge(): ?int
    {
        return $this->maxDonorAge;
    }

    public function setMaxDonorAge(int $maxDonorAge): static
    {
        $this->maxDonorAge = $maxDonorAge;

        return $this;
    }
}