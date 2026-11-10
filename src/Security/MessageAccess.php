<?php

namespace App\Security;

use App\Entity\Message;
use App\Entity\Tenant;
use App\Entity\User;

/**
 * Acesso a uma conversa interna pelo painel. Message não tem tenant: a conversa é do NEPE
 * quando o remetente ou o destinatário da mensagem inicial é usuário dele.
 */
enum MessageAccess
{
    case Allowed;
    /** Conversa de outro NEPE: 404, sem revelar que ela existe */
    case NotFound;
    /** Conversa do NEPE, mas o usuário não participa dela nem é administrador do NEPE: 403 */
    case Forbidden;

    public static function decide(Message $message, User $user, Tenant $domainTenant, bool $isNepeAdmin): self
    {
        $root = $message->getRoot();

        if (!self::belongsTo($root, $domainTenant)) {
            return self::NotFound;
        }

        if ($isNepeAdmin || self::participates($user, $root) || self::participates($user, $message)) {
            return self::Allowed;
        }

        return self::Forbidden;
    }

    /** A conversa (pela mensagem inicial) é do tenant */
    public static function belongsTo(Message $root, Tenant $tenant): bool
    {
        $tenantId = $tenant->getId();
        if ($tenantId === null) {
            return false;
        }

        foreach ([$root->getSender(), $root->getRecipient()] as $member) {
            if ($member?->getTenant()?->getId() === $tenantId) {
                return true;
            }
        }

        return false;
    }

    private static function participates(User $user, Message $message): bool
    {
        foreach ([$message->getSender(), $message->getRecipient()] as $member) {
            if ($member !== null && ($member === $user || ($member->getId() !== null && $member->getId() === $user->getId()))) {
                return true;
            }
        }

        return false;
    }
}
