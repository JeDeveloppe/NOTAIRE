<?php

namespace App\Entity;

use App\Repository\PersonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonRepository::class)]
class Person
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    /**
     * @var Collection<int, Act>
     */
    #[ORM\OneToMany(targetEntity: Act::class, mappedBy: 'beneficiary', orphanRemoval: true)]
    private Collection $actsAsBeneficiary;

    /**
     * @var Collection<int, Act>
     */
    #[ORM\OneToMany(targetEntity: Act::class, mappedBy: 'donor', orphanRemoval: true)]
    private Collection $actsAsDonor;

    #[ORM\ManyToOne(inversedBy: 'peopleOwned')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * **Relation Parents (Côté Propriétaire de la jointure)**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'children')]
    private Collection $parents;

    /**
     * **Relation Enfants (Côté Inverse de la jointure)**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'parents')]
    private Collection $children;

    /**
     * @var Collection<int, Hypothesis>
     */
    #[ORM\OneToMany(targetEntity: Hypothesis::class, mappedBy: 'donor')]
    private Collection $hypothesesIamDonnor;

    /**
     * @var Collection<int, Hypothesis>
     */
    #[ORM\OneToMany(targetEntity: Hypothesis::class, mappedBy: 'beneficiary')]
    private Collection $hypothesesIamBeneficiary;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateOfDeath = null;

    public function __construct()
    {
        $this->actsAsBeneficiary = new ArrayCollection();
        $this->actsAsDonor = new ArrayCollection();
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->hypothesesIamDonnor = new ArrayCollection();
        $this->hypothesesIamBeneficiary = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(\DateTimeImmutable $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return Collection<int, Act>
     */
    public function getActsAsBeneficiary(): Collection
    {
        return $this->actsAsBeneficiary;
    }

    public function addActsAsBeneficiary(Act $actsAsBeneficiary): static
    {
        if (!$this->actsAsBeneficiary->contains($actsAsBeneficiary)) {
            $this->actsAsBeneficiary->add($actsAsBeneficiary);
            $actsAsBeneficiary->setBeneficiary($this);
        }

        return $this;
    }

    public function removeActsAsBeneficiary(Act $actsAsBeneficiary): static
    {
        if ($this->actsAsBeneficiary->removeElement($actsAsBeneficiary)) {
            // set the owning side to null (unless already changed)
            if ($actsAsBeneficiary->getBeneficiary() === $this) {
                $actsAsBeneficiary->setBeneficiary(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Act>
     */
    public function getActsAsDonor(): Collection
    {
        return $this->actsAsDonor;
    }

    public function addActsAsDonor(Act $actsAsDonor): static
    {
        if (!$this->actsAsDonor->contains($actsAsDonor)) {
            $this->actsAsDonor->add($actsAsDonor);
            $actsAsDonor->setDonor($this);
        }

        return $this;
    }

    public function removeActsAsDonor(Act $actsAsDonor): static
    {
        if ($this->actsAsDonor->removeElement($actsAsDonor)) {
            // set the owning side to null (unless already changed)
            if ($actsAsDonor->getDonor() === $this) {
                $actsAsDonor->setDonor(null);
            }
        }

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getParents(): Collection
    {
        return $this->parents;
    }

    public function addParent(self $parent): static
    {
        if (!$this->parents->contains($parent)) {
            $this->parents->add($parent);
        }

        return $this;
    }

    public function removeParent(self $parent): static
    {
        $this->parents->removeElement($parent);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            // ⭐ GESTION BIDIRECTIONNELLE : L'enfant doit pointer vers ce parent.
            $child->addParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            // ⭐ GESTION BIDIRECTIONNELLE : L'enfant doit être retiré de la liste de parents.
            if ($child->getParents()->contains($this)) {
                $child->removeParent($this);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Hypothesis>
     */
    public function gethypothesesIamDonnor(): Collection
    {
        return $this->hypothesesIamDonnor;
    }

    public function addhypothesesIamDonnor(Hypothesis $hypothesesIamDonnor): static
    {
        if (!$this->hypothesesIamDonnor->contains($hypothesesIamDonnor)) {
            $this->hypothesesIamDonnor->add($hypothesesIamDonnor);
            $hypothesesIamDonnor->setDonor($this);
        }

        return $this;
    }

    public function removehypothesesIamDonnor(Hypothesis $hypothesesIamDonnor): static
    {
        if ($this->hypothesesIamDonnor->removeElement($hypothesesIamDonnor)) {
            // set the owning side to null (unless already changed)
            if ($hypothesesIamDonnor->getDonor() === $this) {
                $hypothesesIamDonnor->setDonor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Hypothesis>
     */
    public function getHypothesesIamBeneficiary(): Collection
    {
        return $this->hypothesesIamBeneficiary;
    }

    public function addHypothesesIamBeneficiary(Hypothesis $hypothesesIamBeneficiary): static
    {
        if (!$this->hypothesesIamBeneficiary->contains($hypothesesIamBeneficiary)) {
            $this->hypothesesIamBeneficiary->add($hypothesesIamBeneficiary);
            $hypothesesIamBeneficiary->setBeneficiary($this);
        }

        return $this;
    }

    public function removeHypothesesIamBeneficiary(Hypothesis $hypothesesIamBeneficiary): static
    {
        if ($this->hypothesesIamBeneficiary->removeElement($hypothesesIamBeneficiary)) {
            // set the owning side to null (unless already changed)
            if ($hypothesesIamBeneficiary->getBeneficiary() === $this) {
                $hypothesesIamBeneficiary->setBeneficiary(null);
            }
        }

        return $this;
    }

    public function getDateOfDeath(): ?\DateTimeImmutable
    {
        return $this->dateOfDeath;
    }

    public function setDateOfDeath(?\DateTimeImmutable $dateOfDeath): static
    {
        $this->dateOfDeath = $dateOfDeath;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getFirstName().' '.$this->getLastName();
    }
}