<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    public const PRESENCE_ONLINE = 'online';
    public const PRESENCE_OFFLINE = 'offline';

    private const ALLOWED_ROLES = ['ROLE_USER', 'ROLE_STAFF', 'ROLE_ADMIN'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Username is required.')]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(type: 'json')]
    #[Assert\NotNull]
    #[Assert\All([
        new Assert\Choice(choices: self::ALLOWED_ROLES, message: 'Invalid role "{{ value }}".'),
    ])]
    private array $roles = ['ROLE_USER'];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationRequestedAt = null;

    #[ORM\Column(length: 180, nullable: true, unique: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 20, options: ['default' => 'local'])]
    #[Assert\Choice(choices: ['local', 'google'], message: 'Invalid auth provider "{{ value }}".')]
    private string $authProvider = 'local';

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_ACTIVE])]
    #[Assert\Choice(choices: [self::STATUS_ACTIVE, self::STATUS_DISABLED], message: 'Invalid status "{{ value }}".')]
    private string $status = self::STATUS_ACTIVE;

    private ?string $plainPassword = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(length: 20, options: ['default' => self::PRESENCE_OFFLINE])]
    #[Assert\Choice(choices: [self::PRESENCE_ONLINE, self::PRESENCE_OFFLINE], message: 'Invalid presence status "{{ value }}".')]
    private string $presenceStatus = self::PRESENCE_OFFLINE;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Product::class)]
    private Collection $createdProducts;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Order::class)]
    private Collection $createdOrders;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->createdProducts = new ArrayCollection();
        $this->createdOrders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @return list<string>
     */
    public function getAssignedRoles(): array
    {
        return $this->roles;
    }

    public function isStaffMember(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true)
            || in_array('ROLE_STAFF', $this->roles, true);
    }

    public function isCustomerOnly(): bool
    {
        return !$this->isStaffMember();
    }

    public function getRoles(): array
    {
        $roles = array_values(array_unique($this->roles));
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $normalized = [];

        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '' && in_array($role, self::ALLOWED_ROLES, true)) {
                $normalized[] = $role;
            }
        }

        if (!in_array('ROLE_USER', $normalized, true)) {
            $normalized[] = 'ROLE_USER';
        }

        $this->roles = array_values(array_unique($normalized));
        return $this;
    }

    /**
     * Return friendly labels for roles (for UI display).
     */
    public function getRoleLabels(): array
    {
        $labels = [];
        foreach ($this->getRoles() as $role) {
            switch ($role) {
                case 'ROLE_ADMIN':
                    $labels[] = 'Admin';
                    break;
                case 'ROLE_STAFF':
                    $labels[] = 'Staff';
                    break;
                case 'ROLE_USER':
                    $labels[] = 'Customer';
                    break;
                default:
                    $labels[] = $role; // fallback for custom roles
            }
        }
        return $labels;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        return $data;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        if ($createdAt instanceof \DateTimeImmutable) {
            $this->createdAt = $createdAt;
            return $this;
        }

        $this->createdAt = \DateTimeImmutable::createFromMutable(
            \DateTime::createFromInterface($createdAt)
        );

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->status = $isActive ? self::STATUS_ACTIVE : self::STATUS_DISABLED;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): static
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getEmailVerificationRequestedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationRequestedAt;
    }

    public function setEmailVerificationRequestedAt(?\DateTimeImmutable $emailVerificationRequestedAt): static
    {
        $this->emailVerificationRequestedAt = $emailVerificationRequestedAt;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getAuthProvider(): string
    {
        return $this->authProvider;
    }

    public function setAuthProvider(string $authProvider): static
    {
        $this->authProvider = $authProvider;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $status = strtolower(trim($status));
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_DISABLED], true)) {
            throw new \InvalidArgumentException('Invalid status value.');
        }

        $this->status = $status;
        $this->isActive = $status === self::STATUS_ACTIVE;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getPresenceStatus(): string
    {
        return $this->presenceStatus;
    }

    public function setPresenceStatus(string $presenceStatus): static
    {
        $presenceStatus = strtolower(trim($presenceStatus));
        if (!in_array($presenceStatus, [self::PRESENCE_ONLINE, self::PRESENCE_OFFLINE], true)) {
            throw new \InvalidArgumentException('Invalid presence status value.');
        }

        $this->presenceStatus = $presenceStatus;

        return $this;
    }

    public function isOnline(): bool
    {
        return $this->presenceStatus === self::PRESENCE_ONLINE;
    }

    public function markOnline(): static
    {
        $this->presenceStatus = self::PRESENCE_ONLINE;

        return $this;
    }

    public function markOffline(): static
    {
        $this->presenceStatus = self::PRESENCE_OFFLINE;

        return $this;
    }

    public function markSessionStarted(): static
    {
        $this->markOnline();
        $this->setIsActive(true);

        return $this;
    }

    public function markSessionEnded(): static
    {
        $this->markOffline();
        $this->setIsActive(false);

        return $this;
    }

    public function getPresenceLabel(): string
    {
        return $this->isOnline() ? 'Online' : 'Offline';
    }

    /**
     * Used by Symfony Security during authentication (not for profile display).
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getCreatedProducts(): Collection
    {
        return $this->createdProducts;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getCreatedOrders(): Collection
    {
        return $this->createdOrders;
    }
}
