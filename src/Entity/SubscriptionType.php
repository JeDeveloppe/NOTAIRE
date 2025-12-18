<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use App\Repository\SubscriptionTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: SubscriptionTypeRepository::class)]
class SubscriptionType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, NotarySubscription>
     */
    #[ORM\OneToMany(targetEntity: NotarySubscription::class, mappedBy: 'subscriptionType')]
    private Collection $notarySubscriptions;

     #[ORM\Column(nullable: true)]
    private ?int $duration = null;

     #[ORM\Column]
     private ?int $maxRadius = null;

     #[ORM\Column]
     private ?int $price = null;

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function __construct()
    {
        $this->notarySubscriptions = new ArrayCollection();
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

    /**
     * @return Collection<int, NotarySubscription>
     */
    public function getNotarySubscriptions(): Collection
    {
        return $this->notarySubscriptions;
    }

    public function addNotarySubscription(NotarySubscription $notarySubscription): static
    {
        if (!$this->notarySubscriptions->contains($notarySubscription)) {
            $this->notarySubscriptions->add($notarySubscription);
            $notarySubscription->setSubscriptionType($this);
        }

        return $this;
    }

    public function removeNotarySubscription(NotarySubscription $notarySubscription): static
    {
        if ($this->notarySubscriptions->removeElement($notarySubscription)) {
            // set the owning side to null (unless already changed)
            if ($notarySubscription->getSubscriptionType() === $this) {
                $notarySubscription->setSubscriptionType(null);
            }
        }

        return $this;
    }

    public function getMaxRadius(): ?int
    {
        return $this->maxRadius;
    }

    public function setMaxRadius(int $maxRadius): static
    {
        $this->maxRadius = $maxRadius;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }
}
