<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 50)]
    private ?string $userRole = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    public function getId(): ?int { return $this->id; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): self { $this->username = $username; return $this; }

    public function getUserRole(): ?string { return $this->userRole; }
    public function setUserRole(?string $userRole): self { $this->userRole = $userRole; return $this; }

    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }

    public function getTimestamp(): ?\DateTimeInterface { return $this->timestamp; }
    public function setTimestamp(\DateTimeInterface $timestamp): self { $this->timestamp = $timestamp; return $this; }

    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $entityType): self { $this->entityType = $entityType; return $this; }

    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $entityId): self { $this->entityId = $entityId; return $this; }

    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): self { $this->details = $details; return $this; }
}
