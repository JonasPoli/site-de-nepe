<?php

namespace App\Entity\Trait;

use App\Entity\Enum\ArticleStatus;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Publication status and approval helpers for PublishableInterface entities.
 * The using class declares the $approvals collection, since each one has its own approval entity.
 */
trait HasPublicationWorkflowTrait
{
    #[ORM\Column(length: 20, enumType: ArticleStatus::class, options: ['default' => 'draft'])]
    private ArticleStatus $status = ArticleStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function getStatus(): ArticleStatus { return $this->status; }
    public function setStatus(ArticleStatus $status): static { $this->status = $status; return $this; }

    public function isPublished(): bool { return $this->status === ArticleStatus::Published; }

    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }

    public function getApprovals(): Collection { return $this->approvals; }

    public function getApprovalCount(): int { return $this->approvals->count(); }

    public function isApprovedBy(User $user): bool
    {
        foreach ($this->approvals as $approval) {
            if ($approval->getReviewer() === $user) {
                return true;
            }
        }
        return false;
    }
}
