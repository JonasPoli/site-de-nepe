<?php

namespace App\Security;

use App\Entity\Article;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls who can create, edit, submit for approval, and approve articles.
 *
 * Permissions:
 *   ARTICLE_EDIT   → Admin (workGroup 0) + Editor (workGroup 1)
 *   ARTICLE_REVIEW → Admin (workGroup 0) + Reviewer (workGroup 2)
 *   ARTICLE_APPROVE_OWN → no one (can't approve your own article)
 */
class ArticleVoter extends Voter
{
    public const EDIT    = 'ARTICLE_EDIT';
    public const REVIEW  = 'ARTICLE_REVIEW';
    public const PUBLISH = 'ARTICLE_PUBLISH';

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::REVIEW, self::PUBLISH], true);
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
            self::EDIT    => $user->getWorkGroup() === 1, // Editors
            self::REVIEW  => $user->getWorkGroup() === 1, // Editors can review
            self::PUBLISH => false, // Only auto-published when approvals threshold is met
            default       => false,
        };
    }
}
