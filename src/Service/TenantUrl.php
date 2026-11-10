<?php

namespace App\Service;

use App\Entity\Tenant;

/**
 * Endereço base do site de um NEPE: "https://<domínio>", ou "http://" para localhost e 127.0.0.1
 * (a mesma regra das URLs da API). Usado no login recusado, na página de acesso de outro NEPE,
 * no "entrar como" e no link de redefinição de senha.
 */
final class TenantUrl
{
    public static function base(Tenant $tenant): string
    {
        $domain = rtrim(trim($tenant->getDomain()), '/');

        $local = str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1');

        return ($local ? 'http://' : 'https://') . $domain;
    }
}
