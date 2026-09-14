<?php

namespace App\Contract;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;

/**
 * Content that goes through the tenant approval workflow before becoming public
 * (site and API): draft → pending → published once the tenant's required approvals are reached.
 */
interface PublishableInterface
{
    public function getId(): ?int;

    public function getTenant(): ?Tenant;

    public function getAuthor(): ?User;

    public function getStatus(): ArticleStatus;

    public function setStatus(ArticleStatus $status): static;

    public function getPublishedAt(): ?\DateTimeImmutable;

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static;

    /** @return Collection<int, object> approval entities exposing getReviewer() */
    public function getApprovals(): Collection;

    public function getApprovalCount(): int;

    public function isApprovedBy(User $user): bool;
}
