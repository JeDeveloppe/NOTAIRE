<?php

namespace App\Entity;

use App\Repository\RelationshipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RelationshipRepository::class)]
class Relationship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    /**
     * @var Collection<int, DonationRule>
     */
    #[ORM\OneToMany(targetEntity: DonationRule::class, mappedBy: 'relationship')]
    private Collection $donationRules;

    public function __construct()
    {
        $this->donationRules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    /**
     * @return Collection<int, DonationRule>
     */
    public function getDonationRules(): Collection
    {
        return $this->donationRules;
    }

    public function addDonationRule(DonationRule $donationRule): static
    {
        if (!$this->donationRules->contains($donationRule)) {
            $this->donationRules->add($donationRule);
            $donationRule->setRelationship($this);
        }

        return $this;
    }

    public function removeDonationRule(DonationRule $donationRule): static
    {
        if ($this->donationRules->removeElement($donationRule)) {
            // set the owning side to null (unless already changed)
            if ($donationRule->getRelationship() === $this) {
                $donationRule->setRelationship(null);
            }
        }

        return $this;
    }
}
