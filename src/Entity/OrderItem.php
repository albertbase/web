<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
#[ORM\Index(name: 'IDX_52EA1F09A15A2E17', columns: ['customer_order_id'])]
#[ORM\Index(name: 'IDX_52EA1F094584665A', columns: ['product_id'])]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\OneToOne(targetEntity: CakeCustomization::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CakeCustomization $cakeCustomization = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Order item must belong to an order.')]
    private ?Order $customerOrder = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'Quantity must be at least 1.')]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Subtotal cannot be negative.')]
    private ?float $subtotal = null;

    #[Assert\Callback]
    public function validateSource(ExecutionContextInterface $context): void
    {
        if ($this->product === null && $this->cakeCustomization === null) {
            $context->buildViolation('Order item must reference a product or a cake customization.')
                ->atPath('product')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCakeCustomization(): ?CakeCustomization
    {
        return $this->cakeCustomization;
    }

    public function setCakeCustomization(?CakeCustomization $cakeCustomization): static
    {
        $this->cakeCustomization = $cakeCustomization;

        return $this;
    }

    public function getCustomerOrder(): ?Order
    {
        return $this->customerOrder;
    }

    public function setCustomerOrder(?Order $customerOrder): static
    {
        $this->customerOrder = $customerOrder;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $this->quantity = $quantity;

        return $this;
    }

    public function getSubtotal(): ?float
    {
        return $this->subtotal;
    }

    public function setSubtotal(float $subtotal): static
    {
        if ($subtotal < 0) {
            throw new \InvalidArgumentException('Subtotal cannot be negative.');
        }

        $this->subtotal = round($subtotal, 2);

        return $this;
    }
}
