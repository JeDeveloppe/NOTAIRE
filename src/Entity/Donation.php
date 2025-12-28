<?php

namespace App\Entity;

use App\Repository\DonationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DonationRepository::class)]
#[Assert\Expression(
    "this.getDonor() != this.getBeneficiary()",
    message: "Le donateur et le bénéficiaire ne peuvent pas être la même personne."
)]
class Donation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'donationsGiven')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $donor = null;

    #[ORM\ManyToOne(inversedBy: 'donationsReceived')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $beneficiary = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column]
    private ?int $taxPaid = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDonor(): ?Person
    {
        return $this->donor;
    }

    public function setDonor(?Person $donor): static
    {
        $this->donor = $donor;

        return $this;
    }

    public function getBeneficiary(): ?Person
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?Person $beneficiary): static
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTaxPaid(): ?int
    {
        return $this->taxPaid;
    }

    public function setTaxPaid(int $taxPaid): static
    {
        $this->taxPaid = $taxPaid;

        return $this;
    }
}
