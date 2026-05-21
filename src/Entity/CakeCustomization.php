<?php

namespace App\Entity;

use App\Repository\CakeCustomizationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CakeCustomizationRepository::class)]
class CakeCustomization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Cake size is required.')]
    #[Assert\Length(max: 50)]
    private ?string $size = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Cake flavor is required.')]
    #[Assert\Length(max: 50)]
    private ?string $flavor = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $decorations = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $message = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Cake customization price cannot be negative.')]
    private ?float $price = null;

    #[Assert\Positive(message: 'Quantity must be at least 1.')]
    private int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(string $size): static
    {
        $this->size = trim($size);
        return $this;
    }

    public function getFlavor(): ?string
    {
        return $this->flavor;
    }

    public function setFlavor(string $flavor): static
    {
        $this->flavor = trim($flavor);
        return $this;
    }

    public function getDecorations(): array
    {
        return $this->decorations;
    }

    public function setDecorations(array $decorations): static
    {
        $this->decorations = $decorations;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message !== null ? trim($message) : null;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('Cake customization price cannot be negative.');
        }

        $this->price = $price;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);
        return $this;
    }
}
