<?php

namespace App\Entity;

use App\Repository\SimulationResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SimulationResultRepository::class)]
class SimulationResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'simulationResult', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Hypothesis $sourceHypothesis = null;

    #[ORM\OneToOne(inversedBy: 'simulationResult', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Payment $payment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceHypothesis(): ?Hypothesis
    {
        return $this->sourceHypothesis;
    }

    public function setSourceHypothesis(Hypothesis $sourceHypothesis): static
    {
        $this->sourceHypothesis = $sourceHypothesis;

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): static
    {
        $this->payment = $payment;

        return $this;
    }
}
