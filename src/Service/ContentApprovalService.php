<?php

namespace App\Service;

use App\Contract\PublishableInterface;
use App\Entity\Article;
use App\Entity\ArticleApproval;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Study;
use App\Entity\StudyApproval;
use App\Entity\User;
use App\Entity\VideoSupport;
use App\Entity\VideoSupportApproval;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Approval workflow shared by articles, studies and videos:
 * draft → pending → published once the tenant's required approvals are reached.
 * Approvals must come from another member of the same tenant (or a SuperAdmin).
 */
class ContentApprovalService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** Sends a draft for review. Returns false when the content isn't a draft. */
    public function submit(PublishableInterface $content): bool
    {
        if ($content->getStatus() !== ArticleStatus::Draft) {
            return false;
        }

        $content->setStatus(ArticleStatus::Pending);
        $this->em->flush();

        return true;
    }

    public function approve(PublishableInterface $content, User $reviewer, ?string $comment = null): ApprovalResult
    {
        if ($content->getStatus() !== ArticleStatus::Pending) {
            return ApprovalResult::NotPending;
        }
        if ($this->same($content->getAuthor(), $reviewer)) {
            return ApprovalResult::OwnContent;
        }
        if (!$reviewer->isSuperAdmin() && !$this->same($reviewer->getTenant(), $content->getTenant())) {
            return ApprovalResult::OtherTenant;
        }
        if ($content->isApprovedBy($reviewer)) {
            return ApprovalResult::AlreadyApproved;
        }

        $approval = $this->createApproval($content, $reviewer, $comment);
        $content->getApprovals()->add($approval);
        $this->em->persist($approval);

        $published = $content->getApprovalCount() >= max(1, $content->getTenant()?->getRequiredApprovals() ?? 1);
        if ($published) {
            $content->setStatus(ArticleStatus::Published);
            $content->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        return $published ? ApprovalResult::Published : ApprovalResult::Approved;
    }

    /**
     * Fingerprint of what reviewers approve (text, media and Bible reference).
     * Take it before applying the edit form and pass it to invalidateIfChanged() afterwards.
     */
    public function fingerprint(PublishableInterface $content): string
    {
        $fields = match (true) {
            $content instanceof Study => [
                $content->getTitle(), $content->getDescription(), $content->getMaterialsHtml(),
                $content->getCoverImageUpdatedAt()?->format('U.u'),
            ],
            $content instanceof VideoSupport => [
                $content->getTitle(), $content->getYoutubeId(), $content->getDescription(), $content->getMaterialsHtml(),
                $content->getCustomThumbnail(), $content->getCustomThumbnailUpdatedAt()?->format('U.u'),
            ],
            $content instanceof Article => [
                $content->getTitle(), $content->getShortDescription(), $content->getContent(),
            ],
            default => [],
        };

        if ($content instanceof Study || $content instanceof VideoSupport || $content instanceof Article) {
            array_push($fields, $content->getBibliaBook()?->getId(), $content->getBibliaChapter(), $content->getBibliaVerseStart(), $content->getBibliaVerseEnd());
        }

        return md5(json_encode(array_map(static fn (mixed $value) => is_string($value) ? trim($value) : $value, $fields)));
    }

    /**
     * Sends approved or published content back to draft, dropping its approvals, when what was
     * approved changed. Returns true when that happened; the caller flushes.
     */
    public function invalidateIfChanged(PublishableInterface $content, string $fingerprintBefore): bool
    {
        if ($this->fingerprint($content) === $fingerprintBefore) {
            return false;
        }
        if ($content->getApprovalCount() === 0 && $content->getStatus() !== ArticleStatus::Published) {
            return false;
        }

        foreach ($content->getApprovals()->toArray() as $approval) {
            $content->getApprovals()->removeElement($approval);
            $this->em->remove($approval);
        }

        $content->setStatus(ArticleStatus::Draft);
        $content->setPublishedAt(null);

        return true;
    }

    private function createApproval(PublishableInterface $content, User $reviewer, ?string $comment): ArticleApproval|StudyApproval|VideoSupportApproval
    {
        $approval = match (true) {
            $content instanceof Article      => (new ArticleApproval())->setArticle($content),
            $content instanceof Study        => (new StudyApproval())->setStudy($content),
            $content instanceof VideoSupport => (new VideoSupportApproval())->setVideo($content),
            default => throw new \LogicException(sprintf('There is no approval entity for %s.', $content::class)),
        };

        return $approval->setReviewer($reviewer)->setComment($comment);
    }

    /** Same entity: identical instance, or same database id */
    private function same(?object $a, ?object $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return $a === $b || ($a->getId() !== null && $a->getId() === $b->getId());
    }
}
