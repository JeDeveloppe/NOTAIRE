<?php

namespace App\Entity;

use App\Repository\HypothesisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HypothesisRepository::class)]
class Hypothesis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'hypothesesFromDonnor')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $donor = null;

    #[ORM\ManyToOne(inversedBy: 'hypothesesIamBeneficiary')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $beneficiary = null;

    #[ORM\Column]
    private ?int $simulatedValue = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateOfSimulation = null;

    #[ORM\ManyToOne(inversedBy: 'hypotheses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeAct $typeOfActSimulated = null;

    #[ORM\OneToOne(mappedBy: 'sourceHypothesis', cascade: ['persist', 'remove'])]
    private ?SimulationResult $simulationResult = null;

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

    public function getSimulatedValue(): ?int
    {
        return $this->simulatedValue;
    }

    public function setSimulatedValue(int $simulatedValue): static
    {
        $this->simulatedValue = $simulatedValue;

        return $this;
    }

    public function getDateOfSimulation(): ?\DateTimeImmutable
    {
        return $this->dateOfSimulation;
    }

    public function setDateOfSimulation(\DateTimeImmutable $dateOfSimulation): static
    {
        $this->dateOfSimulation = $dateOfSimulation;

        return $this;
    }

    public function getTypeOfActSimulated(): ?TypeAct
    {
        return $this->typeOfActSimulated;
    }

    public function setTypeOfActSimulated(?TypeAct $typeOfActSimulated): static
    {
        $this->typeOfActSimulated = $typeOfActSimulated;

        return $this;
    }

    public function getSimulationResult(): ?SimulationResult
    {
        return $this->simulationResult;
    }

    public function setSimulationResult(SimulationResult $simulationResult): static
    {
        // set the owning side of the relation if necessary
        if ($simulationResult->getSourceHypothesis() !== $this) {
            $simulationResult->setSourceHypothesis($this);
        }

        $this->simulationResult = $simulationResult;

        return $this;
    }
}
