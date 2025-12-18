<?php

namespace App\Entity;

use App\Repository\NotarySubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotarySubscriptionRepository::class)]
class NotarySubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'notarySubscription', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?NotaryOffice $notaryOffice = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $activedAt = null;

    #[ORM\ManyToOne(inversedBy: 'notarySubscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubscriptionType $subscriptionType = null; // 'standard', 'premium', 'gold'

    public function isActive(): bool {
        return $this->endsAt > new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotaryOffice(): ?NotaryOffice
    {
        return $this->notaryOffice;
    }

    public function setNotaryOffice(NotaryOffice $notaryOffice): static
    {
        $this->notaryOffice = $notaryOffice;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getActivedAt(): ?\DateTimeImmutable
    {
        return $this->activedAt;
    }

    public function setActivedAt(\DateTimeImmutable $activedAt): static
    {
        $this->activedAt = $activedAt;

        return $this;
    }

    public function getSubscriptionType(): ?SubscriptionType
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(?SubscriptionType $subscriptionType): static
    {
        $this->subscriptionType = $subscriptionType;

        return $this;
    }
}
