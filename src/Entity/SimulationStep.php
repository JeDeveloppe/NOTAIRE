<?php

namespace App\Entity;

use App\Repository\SimulationStepRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SimulationStepRepository::class)]
class SimulationStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'simulationSteps')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Simulation $simulation = null;

    #[ORM\ManyToOne(inversedBy: 'simulationSteps')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SimulationStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'simulationSteps')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $changedByUser = null;

    #[ORM\ManyToOne(inversedBy: 'simulationSteps')]
    private ?Notary $changeByNotary = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSimulation(): ?Simulation
    {
        return $this->simulation;
    }

    public function setSimulation(?Simulation $simulation): static
    {
        $this->simulation = $simulation;

        return $this;
    }

    public function getStatus(): ?SimulationStatus
    {
        return $this->status;
    }

    public function setStatus(?SimulationStatus $status): static
    {
        $this->status = $status;

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

    public function getChangedByUser(): ?User
    {
        return $this->changedByUser;
    }

    public function setChangedByUser(?User $changedByUser): static
    {
        $this->changedByUser = $changedByUser;

        return $this;
    }

    public function __toString(): string
    {
        return $this->status?->getLabel() . ' (' . $this->createdAt->format('d/m/Y') . ')';
    }

    public function getChangeByNotary(): ?Notary
    {
        return $this->changeByNotary;
    }

    public function setChangeByNotary(?Notary $changeByNotary): static
    {
        $this->changeByNotary = $changeByNotary;

        return $this;
    }
}
