<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls who can create, edit, submit for approval, and approve content.
 *
 * Permissions:
 *   ARTICLE_EDIT                    → Admin (workGroup 0) + Editor (workGroup 1)
 *   ARTICLE_REVIEW / CONTENT_REVIEW → Admin (workGroup 0) + Editor (workGroup 1) + Reviewer (workGroup 2)
 *   Approving your own content      → no one (checked by ContentApprovalService)
 *
 * CONTENT_REVIEW is the same rule, used for studies and videos.
 */
class ArticleVoter extends Voter
{
    public const EDIT    = 'ARTICLE_EDIT';
    public const REVIEW  = 'ARTICLE_REVIEW';
    public const PUBLISH = 'ARTICLE_PUBLISH';

    public const CONTENT_REVIEW = 'CONTENT_REVIEW';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::REVIEW, self::PUBLISH, self::CONTENT_REVIEW], true);
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Admin (workGroup 0) can do everything
        if ($user->getWorkGroup() === 0) {
            return true;
        }

        return match ($attribute) {
            self::EDIT                         => $user->getWorkGroup() === 1, // Editors
            self::REVIEW, self::CONTENT_REVIEW => in_array($user->getWorkGroup(), [1, 2], true), // Editors and Reviewers
            self::PUBLISH                      => false, // Only auto-published when approvals threshold is met
            default                            => false,
        };
    }
}
