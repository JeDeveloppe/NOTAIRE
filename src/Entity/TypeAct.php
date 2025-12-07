<?php

namespace App\Entity;

use App\Repository\TypeActRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeActRepository::class)]
class TypeAct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $isTaxReductible = null;

    /**
     * @var Collection<int, Act>
     */
    #[ORM\OneToMany(targetEntity: Act::class, mappedBy: 'typeOfAct')]
    private Collection $acts;

    /**
     * @var Collection<int, Hypothesis>
     */
    #[ORM\OneToMany(targetEntity: Hypothesis::class, mappedBy: 'typeOfActSimulated')]
    private Collection $hypotheses;

    public function __construct()
    {
        $this->acts = new ArrayCollection();
        $this->hypotheses = new ArrayCollection();
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

    public function isTaxReductible(): ?bool
    {
        return $this->isTaxReductible;
    }

    public function setIsTaxReductible(bool $isTaxReductible): static
    {
        $this->isTaxReductible = $isTaxReductible;

        return $this;
    }

    /**
     * @return Collection<int, Act>
     */
    public function getActs(): Collection
    {
        return $this->acts;
    }

    public function addAct(Act $act): static
    {
        if (!$this->acts->contains($act)) {
            $this->acts->add($act);
            $act->setTypeOfAct($this);
        }

        return $this;
    }

    public function removeAct(Act $act): static
    {
        if ($this->acts->removeElement($act)) {
            // set the owning side to null (unless already changed)
            if ($act->getTypeOfAct() === $this) {
                $act->setTypeOfAct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Hypothesis>
     */
    public function getHypotheses(): Collection
    {
        return $this->hypotheses;
    }

    public function addHypothesis(Hypothesis $hypothesis): static
    {
        if (!$this->hypotheses->contains($hypothesis)) {
            $this->hypotheses->add($hypothesis);
            $hypothesis->setTypeOfActSimulated($this);
        }

        return $this;
    }

    public function removeHypothesis(Hypothesis $hypothesis): static
    {
        if ($this->hypotheses->removeElement($hypothesis)) {
            // set the owning side to null (unless already changed)
            if ($hypothesis->getTypeOfActSimulated() === $this) {
                $hypothesis->setTypeOfActSimulated(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
