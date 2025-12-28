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

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $lastname = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $birthdate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deathDate = null;

    #[ORM\Column(length: 15)]
    private ?string $gender = null;

    /**
     * @var Collection<int, self>
     * Côté propriétaire de la relation
     */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinTable(name: 'person_relations')]
    private Collection $parents;

    /**
     * @var Collection<int, self>
     * Côté inverse de la relation
     */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'parents')]
    private Collection $children;

    #[ORM\ManyToOne(inversedBy: 'people')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * @var Collection<int, Donation>
     */
    #[ORM\OneToMany(targetEntity: Donation::class, mappedBy: 'donor', cascade: ['remove'], orphanRemoval: true)]
    private Collection $donationsGiven;

    /**
     * @var Collection<int, Donation>
     */
    #[ORM\OneToMany(targetEntity: Donation::class, mappedBy: 'beneficiary', cascade: ['remove'], orphanRemoval: true)]
    private Collection $donationsReceived;

    public function __construct()
    {
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->donationsGiven = new ArrayCollection();
        $this->donationsReceived = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;
        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getBirthdate(): ?\DateTimeImmutable
    {
        return $this->birthdate;
    }

    public function setBirthdate(\DateTimeImmutable $birthdate): static
    {
        $this->birthdate = $birthdate;
        return $this;
    }

    public function getDeathDate(): ?\DateTimeImmutable
    {
        return $this->deathDate;
    }

    public function setDeathDate(?\DateTimeImmutable $deathDate): static
    {
        $this->deathDate = $deathDate;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(string $gender): static
    {
        $this->gender = $gender;
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
            // On maintient la symétrie côté enfant
            $parent->addChild($this);
        }
        return $this;
    }

    public function removeParent(self $parent): static
    {
        if ($this->parents->removeElement($parent)) {
            $parent->removeChild($this);
        }
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
            // On maintient la symétrie côté parent
            $child->addParent($this);
        }
        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            $child->removeParent($this);
        }
        return $this;
    }

    // --- MÉTHODES DE CALCUL GÉNÉALOGIQUE ---

    /**
     * Retourne la fratrie (frères et sœurs) de la personne.
     * @return Person[]
     */
    public function getSiblings(): array
    {
        $siblings = [];

        // On parcourt chaque parent de la personne actuelle
        foreach ($this->getParents() as $parent) {
            // Pour chaque parent, on regarde ses enfants
            foreach ($parent->getChildren() as $child) {
                // On exclut la personne elle-même
                if ($child !== $this) {
                    // On évite les doublons (si les deux parents sont renseignés)
                    if (!in_array($child, $siblings, true)) {
                        $siblings[] = $child;
                    }
                }
            }
        }

        return $siblings;
    }

    /**
     * Retourne les neveux et nièces (enfants des frères et sœurs)
     */
    public function getNephewsAndNieces(): array
    {
        $results = [];
        foreach ($this->getSiblings() as $sibling) {
            foreach ($sibling->getChildren() as $nephew) {
                if (!in_array($nephew, $results, true)) {
                    $results[] = $nephew;
                }
            }
        }
        return $results;
    }

    /**
     * Retourne les oncles et tantes (frères et sœurs des parents)
     */
    public function getUnclesAndAunts(): array
    {
        $results = [];
        foreach ($this->parents as $parent) {
            foreach ($parent->getSiblings() as $uncleOrAunt) {
                if (!in_array($uncleOrAunt, $results, true)) {
                    $results[] = $uncleOrAunt;
                }
            }
        }
        return $results;
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
     * @return Collection<int, Donation>
     */
    public function getDonationsGiven(): Collection
    {
        return $this->donationsGiven;
    }

    public function addDonationsGiven(Donation $donationsGiven): static
    {
        if (!$this->donationsGiven->contains($donationsGiven)) {
            $this->donationsGiven->add($donationsGiven);
            $donationsGiven->setDonor($this);
        }

        return $this;
    }

    public function removeDonationsGiven(Donation $donationsGiven): static
    {
        if ($this->donationsGiven->removeElement($donationsGiven)) {
            // set the owning side to null (unless already changed)
            if ($donationsGiven->getDonor() === $this) {
                $donationsGiven->setDonor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Donation>
     */
    public function getDonationsReceived(): Collection
    {
        return $this->donationsReceived;
    }

    public function addDonationsReceived(Donation $donationsReceived): static
    {
        if (!$this->donationsReceived->contains($donationsReceived)) {
            $this->donationsReceived->add($donationsReceived);
            $donationsReceived->setBeneficiary($this);
        }

        return $this;
    }

    public function removeDonationsReceived(Donation $donationsReceived): static
    {
        if ($this->donationsReceived->removeElement($donationsReceived)) {
            // set the owning side to null (unless already changed)
            if ($donationsReceived->getBeneficiary() === $this) {
                $donationsReceived->setBeneficiary(null);
            }
        }

        return $this;
    }

    /**
     * Retourne l'icône FontAwesome selon le genre
     */
    public function getGenderIcon(): string
    {
        return match (strtolower($this->gender ?? '')) {
            'homme', 'masculin', 'm' => 'fa-mars text-primary',
            'femme', 'féminin', 'f' => 'fa-venus text-danger',
            default => 'fa-genderless text-muted',
        };
    }

 public function getAge(?\DateTimeInterface $atDate = null): int
{
    if (!$this->birthdate) {
        return 0;
    }

    // 1. Déterminer la date de fin de calcul
    // Priorité 1 : La date de simulation saisie ($atDate)
    // Priorité 2 : Si pas de date saisie, on prend la date de décès (si elle existe)
    // Priorité 3 : Sinon, la date du jour
    $endDate = $atDate ?? $this->deathDate ?? new \DateTimeImmutable();

    // 2. Sécurité Notariale : 
    // Si on simule une date FUTURE (ex: 2040) mais que la personne est décédée AVANT (ex: 2030),
    // l'âge doit rester figé à la date du décès. On ne "vieillit" pas après la mort.
    if ($this->deathDate && $endDate > $this->deathDate) {
        $endDate = $this->deathDate;
    }

    // 3. Sécurité Chronologique :
    // Si la date demandée est antérieure à la naissance
    if ($endDate < $this->birthdate) {
        return 0;
    }

    return $this->birthdate->diff($endDate)->y;
}

    /**
     * Retourne une icône de statut (Vivant ou Décédé)
     */
    public function getStatusIcon(): string
    {
        if ($this->deathDate) {
            return 'fa-cross text-secondary'; // Icône de pierre tombale ou croix pour le décès
        }

        return 'fa-heartbeat text-success'; // Icône de vie
    }

    /**
     * Indique si la personne est décédée
     */
    public function isDeceased(): bool
    {
        return $this->deathDate !== null;
    }
}
