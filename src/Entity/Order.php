<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\Index(name: 'IDX_ORDER_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_ORDER_CREATED_AT', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_F5299398B03A8386', columns: ['created_by_id'])]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("ROLE_STAFF")'),
        new GetCollection(security: 'is_granted("ROLE_STAFF")'),
        new Post(security: 'is_granted("ROLE_STAFF")'),
        new Put(security: 'is_granted("ROLE_STAFF")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")')
    ],
    normalizationContext: [
        'groups' => ['order:read']
    ],
    denormalizationContext: [
        'groups' => ['order:write']
    ]
)]
class Order
{
    public const STATUS_PENDING = 'Pending';
    public const STATUS_PAID = 'Paid';
    public const STATUS_SHIPPED = 'Shipped';
    public const STATUS_DELIVERED = 'Delivered';
    public const STATUS_CANCELLED = 'Cancelled';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Customer name is required.')]
    #[Assert\Length(max: 255)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $customerName = null;

    #[ORM\Column(length: 255)]
    #[Assert\Choice(choices: self::ALLOWED_STATUSES, message: 'Invalid order status "{{ value }}".')]
    #[Groups(['order:read', 'order:write'])]
    private ?string $status = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Order total amount cannot be negative.')]
    #[Groups(['order:read', 'order:write'])]
    private ?float $totalAmount = null;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(
        pattern: '/^[0-9+\-\s()]*$/',
        message: 'Customer phone must contain only numbers and common phone characters.'
    )]
    #[Groups(['order:read', 'order:write'])]
    private ?string $customerPhone = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdOrders')]
    #[ORM\JoinColumn(
        name: 'created_by_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'customerOrder',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $orderItems;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_PENDING;
        $this->totalAmount = 0.0;
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = trim($customerName);
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $status = trim($status);

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid order status "%s".', $status));
        }

        $this->status = $status;
        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): static
    {
        if ($totalAmount < 0) {
            throw new \InvalidArgumentException('Order total amount cannot be negative.');
        }

        $this->totalAmount = round($totalAmount, 2);
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setCustomerOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getCustomerOrder() === $this) {
                $orderItem->setCustomerOrder(null);
            }
        }

        return $this;
    }

    /**
     * Backward-compatible alias used by existing templates/controllers.
     *
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->orderItems;
    }

    public function addItem(OrderItem $item): static
    {
        return $this->addOrderItem($item);
    }

    public function removeItem(OrderItem $item): static
    {
        return $this->removeOrderItem($item);
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): static
    {
        $this->customerPhone = $customerPhone !== null ? trim($customerPhone) : null;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): static
    {
        $this->createdBy = $user;
        return $this;
    }
}
