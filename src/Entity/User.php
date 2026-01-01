<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Person>
     */
    #[ORM\OneToMany(targetEntity: Person::class, mappedBy: 'owner')]
    private Collection $people;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $city = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Simulation $simulation = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Notary $notary = null;

    /**
     * @var Collection<int, SimulationStep>
     */
    #[ORM\OneToMany(targetEntity: SimulationStep::class, mappedBy: 'changedBy')]
    private Collection $simulationSteps;

    public function __construct()
    {
        $this->people = new ArrayCollection();
        $this->simulationSteps = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    /**
     * @return Collection<int, Person>
     */
    public function getPeople(): Collection
    {
        return $this->people;
    }

    public function addPerson(Person $person): static
    {
        if (!$this->people->contains($person)) {
            $this->people->add($person);
            $person->setOwner($this);
        }

        return $this;
    }

    public function removePerson(Person $person): static
    {
        if ($this->people->removeElement($person)) {
            // set the owning side to null (unless already changed)
            if ($person->getOwner() === $this) {
                $person->setOwner(null);
            }
        }

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

    public function getSimulation(): ?Simulation
    {
        return $this->simulation;
    }

    public function setSimulation(Simulation $simulation): static
    {
        // set the owning side of the relation if necessary
        if ($simulation->getUser() !== $this) {
            $simulation->setUser($this);
        }

        $this->simulation = $simulation;

        return $this;
    }

    public function getNotary(): ?Notary
    {
        return $this->notary;
    }

    public function setNotary(Notary $notary): static
    {
        // set the owning side of the relation if necessary
        if ($notary->getUser() !== $this) {
            $notary->setUser($this);
        }

        $this->notary = $notary;

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
            $simulationStep->setChangedBy($this);
        }

        return $this;
    }

    public function removeSimulationStep(SimulationStep $simulationStep): static
    {
        if ($this->simulationSteps->removeElement($simulationStep)) {
            // set the owning side to null (unless already changed)
            if ($simulationStep->getChangedBy() === $this) {
                $simulationStep->setChangedBy(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
