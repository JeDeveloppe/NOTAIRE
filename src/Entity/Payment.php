<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $transactionId = null;

    #[ORM\Column]
    private ?int $amountPaid = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateOfPayment = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PaymentStatus $status = null;

    #[ORM\OneToOne(mappedBy: 'payment', cascade: ['persist', 'remove'])]
    private ?SimulationResult $simulationResult = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionId;
    }

    public function setTransactionId(int $transactionId): static
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function getAmountPaid(): ?int
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(int $amountPaid): static
    {
        $this->amountPaid = $amountPaid;

        return $this;
    }

    public function getDateOfPayment(): ?\DateTimeImmutable
    {
        return $this->dateOfPayment;
    }

    public function setDateOfPayment(\DateTimeImmutable $dateOfPayment): static
    {
        $this->dateOfPayment = $dateOfPayment;

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getStatus(): ?PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(?PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSimulationResult(): ?SimulationResult
    {
        return $this->simulationResult;
    }

    public function setSimulationResult(SimulationResult $simulationResult): static
    {
        // set the owning side of the relation if necessary
        if ($simulationResult->getPayment() !== $this) {
            $simulationResult->setPayment($this);
        }

        $this->simulationResult = $simulationResult;

        return $this;
    }
}
