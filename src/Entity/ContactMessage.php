<?php

namespace App\Entity;

use App\Contract\TenantAwareInterface;
use App\Repository\ContactMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
class ContactMessage implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 255)]
    private string $senderName = '';

    #[ORM\Column(length: 255)]
    private string $senderEmail = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isRead = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }

    public function getSenderName(): string { return $this->senderName; }
    public function setSenderName(string $senderName): static { $this->senderName = $senderName; return $this; }

    public function getSenderEmail(): string { return $this->senderEmail; }
    public function setSenderEmail(string $senderEmail): static { $this->senderEmail = $senderEmail; return $this; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): static { $this->isRead = $isRead; return $this; }
}
