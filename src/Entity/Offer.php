<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $baseNotariesCount = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $isAddon = null;

    /**
     * @var Collection<int, OfferPrice>
     */
    #[ORM\OneToMany(targetEntity: OfferPrice::class, mappedBy: 'offer')]
    private Collection $offerPrices;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'offer')]
    private Collection $subscriptions;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $badge = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column]
    private ?bool $isOnWebSite = null;

    public function __construct()
    {
        $this->offerPrices = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->subscriptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getBaseNotariesCount(): ?int
    {
        return $this->baseNotariesCount;
    }

    public function setBaseNotariesCount(int $baseNotariesCount): static
    {
        $this->baseNotariesCount = $baseNotariesCount;

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

    public function isAddon(): ?bool
    {
        return $this->isAddon;
    }

    public function setIsAddon(bool $isAddon): static
    {
        $this->isAddon = $isAddon;

        return $this;
    }

    /**
     * @return Collection<int, OfferPrice>
     */
    public function getOfferPrices(): Collection
    {
        return $this->offerPrices;
    }

    public function addOfferPrice(OfferPrice $offerPrice): static
    {
        if (!$this->offerPrices->contains($offerPrice)) {
            $this->offerPrices->add($offerPrice);
            $offerPrice->setOffer($this);
        }

        return $this;
    }

    public function removeOfferPrice(OfferPrice $offerPrice): static
    {
        if ($this->offerPrices->removeElement($offerPrice)) {
            // set the owning side to null (unless already changed)
            if ($offerPrice->getOffer() === $this) {
                $offerPrice->setOffer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setOffer($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getOffer() === $this) {
                $subscription->setOffer(null);
            }
        }

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
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

    public function getCurrentPrice(): ?OfferPrice
    {
        $now = new \DateTimeImmutable();
        foreach ($this->offerPrices as $price) {
            // Un prix est valide si : commencé avant maintenant ET (pas de fin OU finit après maintenant)
            if ($price->getStartAt() <= $now && ($price->getEndAt() === null || $price->getEndAt() >= $now)) {
                return $price;
            }
        }

        return $this->offerPrices->last() ?: null; // Repli sur le dernier au cas où
    }

    public function isOnWebSite(): ?bool
    {
        return $this->isOnWebSite;
    }

    public function setIsOnWebSite(bool $isOnWebSite): static
    {
        $this->isOnWebSite = $isOnWebSite;

        return $this;
    }
}
