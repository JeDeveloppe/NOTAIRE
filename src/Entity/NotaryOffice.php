<?php

namespace App\Entity;

use App\Repository\NotaryOfficeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotaryOfficeRepository::class)]
class NotaryOffice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $streetNumber = null;

    #[ORM\ManyToOne]
    private ?SubdivisionIndicator $subdivisionIndicator = null;

    #[ORM\Column(length: 255)]
    private ?string $street = null;

    #[ORM\Column(length: 15)]
    private ?string $phone = null;

    #[ORM\OneToOne(mappedBy: 'NotaryOffice', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'notaryOffices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $city = null;

    #[ORM\OneToOne(mappedBy: 'notaryOffice', cascade: ['persist', 'remove'])]
    private ?NotarySubscription $notarySubscription = null;

    #[ORM\Column(nullable: true)]
    private ?int $radius = null;

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

    public function getStreetNumber(): ?int
    {
        return $this->streetNumber;
    }

    public function setStreetNumber(int $streetNumber): static
    {
        $this->streetNumber = $streetNumber;

        return $this;
    }

    public function getSubdivisionIndicator(): ?SubdivisionIndicator
    {
        return $this->subdivisionIndicator;
    }

    public function setSubdivisionIndicator(?SubdivisionIndicator $subdivisionIndicator): static
    {
        $this->subdivisionIndicator = $subdivisionIndicator;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // unset the owning side of the relation if necessary
        if ($user === null && $this->user !== null) {
            $this->user->setNotaryOffice(null);
        }

        // set the owning side of the relation if necessary
        if ($user !== null && $user->getNotaryOffice() !== $this) {
            $user->setNotaryOffice($this);
        }

        $this->user = $user;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getNotarySubscription(): ?NotarySubscription
    {
        return $this->notarySubscription;
    }

    public function setNotarySubscription(NotarySubscription $notarySubscription): static
    {
        // set the owning side of the relation if necessary
        if ($notarySubscription->getNotaryOffice() !== $this) {
            $notarySubscription->setNotaryOffice($this);
        }

        $this->notarySubscription = $notarySubscription;

        return $this;
    }

    public function isPremium(): bool {
        return $this->notarySubscription !== null && $this->notarySubscription->isActive();
    }

    public function getRadius(): ?int
    {
        return $this->radius;
    }

    public function setRadius(?int $radius): static
    {
        $this->radius = $radius;

        return $this;
    }
}
