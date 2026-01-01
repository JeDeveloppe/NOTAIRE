<?php

namespace App\Entity;

use App\Repository\SimulationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SimulationRepository::class)]
class Simulation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25, unique: true)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(inversedBy: 'simulation', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'simulations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Notary $reservedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reservedAt = null;

    /**
     * Date à laquelle le dossier redevient libre après un abandon ou une expiration.
     * Reste NULL à la création car le dossier n'a jamais été réservé.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $availableAt = null;

    #[ORM\ManyToOne(inversedBy: 'simulations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SimulationStatus $status = null;

    /**
     * @var Collection<int, SimulationStep>
     */
    #[ORM\OneToMany(targetEntity: SimulationStep::class, mappedBy: 'simulation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $simulationSteps;

    public function __construct()
    {
        $this->simulationSteps = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        
        // Initialisation à NULL par défaut (Logique métier cohérente)
        $this->reservedAt = null;
        $this->availableAt = null;
        
        // Génération automatique du code type SIM25-A8F2-99X1-DON
        $this->reference = $this->generateSmartCode('DON');
    }

    /**
     * Génère une référence unique : SIM(Année)-(Aléatoire)-(Aléatoire)-(Type)
     */
    private function generateSmartCode(string $type): string
    {
        $year = (new \DateTime())->format('y');
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        
        $part = function() use ($chars) {
            $out = '';
            for ($i = 0; $i < 4; $i++) {
                $out .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $out;
        };

        return sprintf('SIM%s-%s-%s-%s', $year, $part(), $part(), $type);
    }

    // --- GETTERS & SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getReservedBy(): ?Notary
    {
        return $this->reservedBy;
    }

    public function setReservedBy(?Notary $reservedBy): static
    {
        $this->reservedBy = $reservedBy;
        return $this;
    }

    public function getReservedAt(): ?\DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function setReservedAt(?\DateTimeImmutable $reservedAt): static
    {
        $this->reservedAt = $reservedAt;
        return $this;
    }

    public function getAvailableAt(): ?\DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function setAvailableAt(?\DateTimeImmutable $availableAt): static
    {
        $this->availableAt = $availableAt;
        return $this;
    }

    public function getStatus(): ?SimulationStatus
    {
        return $this->status;
    }

    public function setStatus(?SimulationStatus $status): static
    {
        $this->status = $status;
        return $this;
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
            $simulationStep->setSimulation($this);
        }
        return $this;
    }

    public function removeSimulationStep(SimulationStep $simulationStep): static
    {
        if ($this->simulationSteps->removeElement($simulationStep)) {
            if ($simulationStep->getSimulation() === $this) {
                $simulationStep->setSimulation(null);
            }
        }
        return $this;
    }
}