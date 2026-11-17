<?php

namespace App\Enum;

/**
 * O papel de um usuário no painel, como ele deve ser mostrado.
 *
 * Existe para a regra ficar num lugar só: antes, a listagem do super admin
 * decidia "Super Admin" olhando apenas se o usuário estava sem NEPE, enquanto
 * `User::getRoles()` exige também o grupo 0 — e um editor sem NEPE aparecia
 * com um poder que não tem.
 */
enum PanelRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Editor = 'editor';
    case Reviewer = 'reviewer';

    /**
     * Sem NEPE e fora do grupo 0: o login não aceita esse usuário em domínio
     * nenhum e ele não é super admin, então não entra em painel algum.
     */
    case Inconsistent = 'inconsistent';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Reviewer => 'Revisor',
            self::Inconsistent => 'Cadastro inconsistente',
        };
    }

    public function isInconsistent(): bool
    {
        return $this === self::Inconsistent;
    }
}
