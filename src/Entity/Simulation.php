<?php

namespace App\Entity;

use App\Repository\SimulationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SimulationRepository::class)]
class Simulation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(inversedBy: 'simulation', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'simulations')]
    private ?Notary $reservedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $reservedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $availableAt = null;

    #[ORM\ManyToOne(inversedBy: 'simulations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SimulationStatus $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getReservedBy(): ?Notary
    {
        return $this->reservedBy;
    }

    public function setReservedBy(?Notary $reservedBy): static
    {
        $this->reservedBy = $reservedBy;

        return $this;
    }

    public function getReservedAt(): ?\DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function setReservedAt(\DateTimeImmutable $reservedAt): static
    {
        $this->reservedAt = $reservedAt;

        return $this;
    }

    public function getAvailableAt(): ?\DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function setAvailableAt(\DateTimeImmutable $availableAt): static
    {
        $this->availableAt = $availableAt;

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
}
