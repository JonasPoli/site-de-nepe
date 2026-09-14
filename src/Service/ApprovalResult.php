<?php

namespace App\Service;

/** Outcome of ContentApprovalService::approve() */
enum ApprovalResult
{
    /** Approval recorded; more approvals are still needed */
    case Approved;
    /** Approval recorded and the tenant's required approvals were reached */
    case Published;
    case NotPending;
    case OwnContent;
    case OtherTenant;
    case AlreadyApproved;

    /** @return array{0: string, 1: string} flash type and message */
    public function flash(): array
    {
        return match ($this) {
            self::Approved        => ['success', 'Aprovação registrada. Ainda faltam aprovações para publicar.'],
            self::Published       => ['success', 'Aprovação registrada. O conteúdo foi publicado.'],
            self::NotPending      => ['warning', 'Só é possível aprovar conteúdo que foi enviado para aprovação.'],
            self::OwnContent      => ['error', 'Você não pode aprovar o próprio conteúdo.'],
            self::OtherTenant     => ['error', 'Só membros deste site podem aprovar este conteúdo.'],
            self::AlreadyApproved => ['warning', 'Você já aprovou este conteúdo.'],
        };
    }
}
