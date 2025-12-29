<?php

namespace App\Entity;

use App\Repository\DonationRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DonationRuleRepository::class)]
class DonationRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column]
    private ?int $allowanceAmount = null;

    #[ORM\Column]
    private ?int $frequencyYears = null;

    #[ORM\Column]
    private ?int $donorMaxAge = null;

    #[ORM\Column]
    private ?int $receiverMinAge = null;

    #[ORM\Column]
    private ?bool $isCumulative = null;

    /**
     * Ce champ permet de savoir quel barème (brackets) utiliser
     * ex: 'progressif_direct', 'freres_soeurs', 'tiers'
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $taxSystem = null;

    #[ORM\ManyToOne(inversedBy: 'donationRules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Relationship $relationship = null;

    #[ORM\Column]
    private ?bool $isBidirectional = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getAllowanceAmount(): ?int
    {
        return $this->allowanceAmount;
    }

    public function setAllowanceAmount(int $allowanceAmount): static
    {
        $this->allowanceAmount = $allowanceAmount;
        return $this;
    }

    public function getFrequencyYears(): ?int
    {
        return $this->frequencyYears;
    }

    public function setFrequencyYears(int $frequencyYears): static
    {
        $this->frequencyYears = $frequencyYears;
        return $this;
    }

    public function getDonorMaxAge(): ?int
    {
        return $this->donorMaxAge;
    }

    public function setDonorMaxAge(int $donorMaxAge): static
    {
        $this->donorMaxAge = $donorMaxAge;
        return $this;
    }

    public function getReceiverMinAge(): ?int
    {
        return $this->receiverMinAge;
    }

    public function setReceiverMinAge(int $receiverMinAge): static
    {
        $this->receiverMinAge = $receiverMinAge;
        return $this;
    }

    public function isCumulative(): ?bool
    {
        return $this->isCumulative;
    }

    public function setIsCumulative(bool $isCumulative): static
    {
        $this->isCumulative = $isCumulative;
        return $this;
    }

    public function getTaxSystem(): ?string
    {
        return $this->taxSystem;
    }

    public function setTaxSystem(?string $taxSystem): static
    {
        $this->taxSystem = $taxSystem;
        return $this;
    }

    public function getRelationship(): ?Relationship
    {
        return $this->relationship;
    }

    public function setRelationship(?Relationship $relationship): static
    {
        $this->relationship = $relationship;
        return $this;
    }

    public function isBidirectional(): ?bool
    {
        return $this->isBidirectional;
    }

    public function setIsBidirectional(bool $isBidirectional): static
    {
        $this->isBidirectional = $isBidirectional;

        return $this;
    }
}