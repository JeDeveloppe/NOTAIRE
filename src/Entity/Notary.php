<?php

namespace App\Entity;

use App\Repository\NotaryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotaryRepository::class)]
class Notary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Simulation>
     */
    #[ORM\OneToMany(targetEntity: Simulation::class, mappedBy: 'reservedBy')]
    private Collection $simulations;

    #[ORM\OneToOne(inversedBy: 'notary', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(options: ["default" => 100])]
    private ?int $score = 100;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'notary')]
    private Collection $subscriptions;

    /**
     * @var Collection<int, SelectedZipCode>
     */
    #[ORM\OneToMany(targetEntity: SelectedZipCode::class, mappedBy: 'notary')]
    private Collection $selectedZipCodes;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\ManyToOne(inversedBy: 'notaries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $city = null;

    #[ORM\Column(length: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 14)]
    private ?string $siret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private ?bool $isConfirmed = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * @var Collection<int, SimulationStep>
     */
    #[ORM\OneToMany(targetEntity: SimulationStep::class, mappedBy: 'changeByNotary')]
    private Collection $simulationSteps;

    public function __construct()
    {
        $this->simulations = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->selectedZipCodes = new ArrayCollection();
        $this->simulationSteps = new ArrayCollection();
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
     * @return Collection<int, Simulation>
     */
    public function getSimulations(): Collection
    {
        return $this->simulations;
    }

    public function addSimulation(Simulation $simulation): static
    {
        if (!$this->simulations->contains($simulation)) {
            $this->simulations->add($simulation);
            $simulation->setReservedBy($this);
        }

        return $this;
    }

    public function removeSimulation(Simulation $simulation): static
    {
        if ($this->simulations->removeElement($simulation)) {
            // set the owning side to null (unless already changed)
            if ($simulation->getReservedBy() === $this) {
                $simulation->setReservedBy(null);
            }
        }

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

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

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
            $subscription->setNotary($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getNotary() === $this) {
                $subscription->setNotary(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SelectedZipCode>
     */
    public function getSelectedZipCodes(): Collection
    {
        return $this->selectedZipCodes;
    }

    public function addSelectedZipCode(SelectedZipCode $selectedZipCode): static
    {
        if (!$this->selectedZipCodes->contains($selectedZipCode)) {
            $this->selectedZipCodes->add($selectedZipCode);
            $selectedZipCode->setNotary($this);
        }

        return $this;
    }

    public function removeSelectedZipCode(SelectedZipCode $selectedZipCode): static
    {
        if ($this->selectedZipCodes->removeElement($selectedZipCode)) {
            // set the owning side to null (unless already changed)
            if ($selectedZipCode->getNotary() === $this) {
                $selectedZipCode->setNotary(null);
            }
        }

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        // 1. On supprime tout ce qui n'est pas un chiffre
        $digits = preg_replace('/\D/', '', $phone);

        // 2. On découpe par paires de 2 chiffres
        $pairs = str_split($digits, 2);

        // 3. On rassemble avec des virgules
        $this->phone = implode('.', $pairs);

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function isConfirmed(): ?bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(bool $isConfirmed): static
    {
        $this->isConfirmed = $isConfirmed;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getActiveSubscription(): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->isActive()) {
                return $subscription;
            }
        }
        return null;
    }

    public function __toString()
    {
        return $this->name;
    }

    /**
     * @return Collection<int, SimulationStep>
     */
    public function getSimulationSteps(): Collection
    {
        return $this->simulationSteps;
    }

    public function addSimulationStep(SimulationStep $simulationStep): static
    {
        if (!$this->simulationSteps->contains($simulationStep)) {
            $this->simulationSteps->add($simulationStep);
            $simulationStep->setChangeByNotary($this);
        }

        return $this;
    }

    public function removeSimulationStep(SimulationStep $simulationStep): static
    {
        if ($this->simulationSteps->removeElement($simulationStep)) {
            // set the owning side to null (unless already changed)
            if ($simulationStep->getChangeByNotary() === $this) {
                $simulationStep->setChangeByNotary(null);
            }
        }

        return $this;
    }
}
