<?php

namespace App\Security;

/**
 * Regras de uma senha aceita pelo sistema, em um só lugar.
 *
 * Vale em todo ponto que define senha: perfil do usuário (/admin/perfil), criação de usuário
 * pelo painel do NEPE (/admin/editors/new), criação e edição pelo super admin e redefinição
 * pelo link enviado por e-mail. Senhas gravadas antes desta regra continuam válidas no login:
 * a política só é conferida quando uma senha nova é definida.
 *
 * A lista de requirements() é a mesma que as telas mostram e que o JavaScript confere enquanto
 * o usuário digita (templates/_password_fields.html.twig), para não existirem duas listas.
 * O JS é conveniência: quem decide é sempre o servidor.
 */
final class PasswordPolicy
{
    /** "Mais de 8 caracteres": 9 é o mínimo aceito. */
    public const MIN_LENGTH = 9;

    /** Teto do que chega a ser hasheado, para não aceitar um envio absurdo. */
    public const MAX_LENGTH = 4096;

    /**
     * Requisitos na ordem em que aparecem na tela.
     *
     * `regex` é escrita em sintaxe compatível com PCRE e com JavaScript, porque a mesma
     * expressão é entregue ao navegador. `\p{L}` e `\p{N}` exigem o modificador `u` no PHP
     * e a flag `u` no JavaScript.
     *
     * @return list<array{key: string, label: string, regex: string}>
     */
    public static function requirements(): array
    {
        return [
            [
                'key'   => 'length',
                'label' => sprintf('Mais de %d caracteres', self::MIN_LENGTH - 1),
                'regex' => sprintf('^.{%d,}$', self::MIN_LENGTH),
            ],
            [
                'key'   => 'lowercase',
                'label' => 'Uma letra minúscula',
                'regex' => '\p{Ll}',
            ],
            [
                'key'   => 'uppercase',
                'label' => 'Uma letra maiúscula',
                'regex' => '\p{Lu}',
            ],
            [
                'key'   => 'special',
                'label' => 'Um caractere especial (ex.: ! @ # $ % & *)',
                'regex' => '[^\p{L}\p{N}]',
            ],
        ];
    }

    /**
     * Requisitos que a senha não cumpre, na ordem da tela. Lista vazia = senha aceita.
     *
     * @return list<string> rótulos em pt-BR
     */
    public static function unmetRequirements(string $password): array
    {
        if ($password === '') {
            return array_map(static fn (array $r): string => $r['label'], self::requirements());
        }

        if (mb_strlen($password) > self::MAX_LENGTH) {
            return ['A senha é longa demais'];
        }

        $unmet = [];
        foreach (self::requirements() as $requirement) {
            // O tamanho é contado em caracteres (mb_strlen), não em bytes: "ação" tem 4 letras.
            $satisfied = $requirement['key'] === 'length'
                ? mb_strlen($password) >= self::MIN_LENGTH
                : preg_match('/' . $requirement['regex'] . '/u', $password) === 1;

            if (!$satisfied) {
                $unmet[] = $requirement['label'];
            }
        }

        return $unmet;
    }

    public static function isSatisfiedBy(string $password): bool
    {
        return self::unmetRequirements($password) === [];
    }

    /**
     * Aviso pronto para o flash, listando o que falta. Null quando a senha é aceita.
     */
    public static function errorMessage(string $password): ?string
    {
        $unmet = self::unmetRequirements($password);
        if ($unmet === []) {
            return null;
        }

        return 'A senha não cumpre os requisitos: ' . implode('; ', $unmet) . '.';
    }
}
