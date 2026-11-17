<?php

namespace App\Security;

/**
 * Regras do que o próprio usuário pode mudar em /admin/perfil.
 *
 * Fica fora do controller para poder ser testada sem banco e sem HTTP: o controller
 * só lê o request, aplica estas regras e grava.
 */
final class ProfilePolicy
{
    /** Campos que o usuário altera no próprio perfil. Papel (workGroup) e NEPE (tenant) não estão aqui. */
    public const EDITABLE_FIELDS = ['name', 'email', 'username', 'whatsapp', 'photo', 'password'];

    /** Campos que o perfil nunca grava, mesmo que cheguem no envio do formulário. */
    public const IGNORED_FIELDS = ['workGroup', 'tenant', 'roles', 'id'];

    public const ALLOWED_PHOTO_MIMES = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'];

    /** 4 MB: o disco do servidor é compartilhado e já encheu duas vezes */
    public const MAX_PHOTO_BYTES = 4 * 1024 * 1024;

    public const WHATSAPP_MAX_LENGTH = 30;

    public static function isEditable(string $field): bool
    {
        return in_array($field, self::EDITABLE_FIELDS, true);
    }

    /**
     * Mudar o usuário de login ou a senha invalida o token da sessão no Symfony
     * (ContextListener::hasUserChanged compara identificador e hash da senha):
     * é preciso entrar de novo.
     */
    public static function requiresNewLogin(?string $oldUsername, ?string $newUsername, bool $passwordChanged): bool
    {
        return $passwordChanged || trim((string) $oldUsername) !== trim((string) $newUsername);
    }

    /** Texto do campo, sem espaços nas pontas; vazio vira null. */
    public static function normalizeOptional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function normalizeWhatsapp(?string $value): ?string
    {
        $value = self::normalizeOptional($value);
        if ($value === null) {
            return null;
        }

        // Espaços repetidos viram um só; o formato fica como o usuário escreveu, porque é texto público
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return mb_substr($value, 0, self::WHATSAPP_MAX_LENGTH);
    }

    /** Motivo da recusa da foto, ou null quando o arquivo serve. */
    public static function photoError(?string $mimeType, ?int $sizeInBytes): ?string
    {
        if ($mimeType === null || !in_array(strtolower($mimeType), self::ALLOWED_PHOTO_MIMES, true)) {
            return 'A foto precisa ser uma imagem JPEG, PNG ou WebP.';
        }

        if ($sizeInBytes !== null && $sizeInBytes > self::MAX_PHOTO_BYTES) {
            return sprintf('A foto precisa ter no máximo %d MB.', (int) (self::MAX_PHOTO_BYTES / 1024 / 1024));
        }

        return null;
    }
}
