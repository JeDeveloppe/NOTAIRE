<?php

namespace App\Entity;

use App\Repository\ActRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActRepository::class)]
class Act
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'actsAsBeneficiary')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $beneficiary = null;

    #[ORM\ManyToOne(inversedBy: 'actsAsDonor')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $donor = null;

    #[ORM\ManyToOne(inversedBy: 'acts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeAct $typeOfAct = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateOfAct = null;

    #[ORM\Column]
    private ?int $value = null;

    #[ORM\Column]
    private ?int $consumedAbatement = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDonor(): ?Person
    {
        return $this->donor;
    }

    public function setDonor(?Person $donor): static
    {
        $this->donor = $donor;

        return $this;
    }

    public function getTypeOfAct(): ?TypeAct
    {
        return $this->typeOfAct;
    }

    public function setTypeOfAct(?TypeAct $typeOfAct): static
    {
        $this->typeOfAct = $typeOfAct;

        return $this;
    }

    public function getDateOfAct(): ?\DateTimeImmutable
    {
        return $this->dateOfAct;
    }

    public function setDateOfAct(\DateTimeImmutable $dateOfAct): static
    {
        $this->dateOfAct = $dateOfAct;

        return $this;
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function setValue(int $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getConsumedAbatement(): ?int
    {
        return $this->consumedAbatement;
    }

    public function setConsumedAbatement(int $consumedAbatement): static
    {
        $this->consumedAbatement = $consumedAbatement;

        return $this;
    }
}
