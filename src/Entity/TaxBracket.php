<?php

namespace App\Entity;

use App\Repository\TaxBracketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxBracketRepository::class)]
class TaxBracket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null; // 'direct', 'siblings', 'nephews', 'third_party'

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $amountLimit = null; // null pour la dernière tranche (infini)

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $rate = null; // 0.05 pour 5%

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAmountLimit(): ?float
    {
        return $this->amountLimit !== null ? (float) $this->amountLimit : null;
    }

    public function setAmountLimit(?float $amountLimit): static
    {
        $this->amountLimit = $amountLimit;
        return $this;
    }

    public function getRate(): ?float
    {
        return (float) $this->rate;
    }

    public function setRate(float $rate): static
    {
        $this->rate = $rate;
        return $this;
    }
}