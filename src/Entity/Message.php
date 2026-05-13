<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Internal messaging between users within the same tenant.
 * Supports threaded conversations (self-referential parent/replies).
 * Can optionally be linked to an Article via contextType/contextId.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    /**
     * Lifecycle: unread → read → replied / ignored / resolved
     */
    #[ORM\Column(length: 20, options: ['default' => 'unread'])]
    private string $status = 'unread';

    /** e.g. 'article' */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contextType = null;

    /** e.g. ['id' => 42] */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contextId = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['remove'])]
    #[ORM\OrderBy(['sentAt' => 'ASC'])]
    private Collection $replies;

    public function __construct()
    {
        $this->sentAt  = new \DateTimeImmutable();
        $this->replies = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): static { $this->sender = $sender; return $this; }

    public function getRecipient(): ?User { return $this->recipient; }
    public function setRecipient(?User $recipient): static { $this->recipient = $recipient; return $this; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $subject): static { $this->subject = $subject; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getSentAt(): \DateTimeImmutable { return $this->sentAt; }

    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $readAt): static { $this->readAt = $readAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getContextType(): ?string { return $this->contextType; }
    public function setContextType(?string $contextType): static { $this->contextType = $contextType; return $this; }

    public function getContextId(): ?array { return $this->contextId; }
    public function setContextId(?array $contextId): static { $this->contextId = $contextId; return $this; }

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): static { $this->parent = $parent; return $this; }

    /** @return Collection<int, Message> */
    public function getReplies(): Collection { return $this->replies; }

    public function isUnread(): bool { return $this->status === 'unread'; }
    public function isResolved(): bool { return $this->status === 'resolved'; }

    /** Walk up the parent chain and return the root message */
    public function getRoot(): self
    {
        $root = $this;
        while ($root->getParent() !== null) {
            $root = $root->getParent();
        }
        return $root;
    }
}
