<?php

namespace App\Entity;

use App\Repository\ArticleApprovalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleApprovalRepository::class)]
class ArticleApproval
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reviewer = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $approvedAt;

    public function __construct()
    {
        $this->approvedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): static { $this->article = $article; return $this; }

    public function getReviewer(): ?User { return $this->reviewer; }
    public function setReviewer(?User $reviewer): static { $this->reviewer = $reviewer; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }

    public function getApprovedAt(): \DateTimeImmutable { return $this->approvedAt; }
}
