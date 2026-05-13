<?php

namespace App\Entity\Enum;

enum ArticleStatus: string
{
    case Draft     = 'draft';
    case Pending   = 'pending';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Rascunho',
            self::Pending   => 'Aguardando aprovação',
            self::Published => 'Publicado',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft     => 'neutral',
            self::Pending   => 'warning',
            self::Published => 'success',
        };
    }
}
