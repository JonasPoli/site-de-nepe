<?php
declare(strict_types=1);

namespace App\Twig;

use App\Security\PasswordPolicy;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Leva os requisitos de senha da PasswordPolicy para as telas, de modo que a lista
 * mostrada e conferida pelo JavaScript venha do mesmo lugar que o servidor usa.
 *
 * No Twig:
 *   {% for r in password_requirements() %}{{ r.label }}{% endfor %}
 *   {{ password_min_length() }}
 */
class PasswordPolicyExtension extends AbstractExtension
{
    /** @return TwigFunction[] */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('password_requirements', [$this, 'requirements']),
            new TwigFunction('password_min_length', [$this, 'minLength']),
        ];
    }

    /** @return list<array{key: string, label: string, regex: string}> */
    public function requirements(): array
    {
        return PasswordPolicy::requirements();
    }

    public function minLength(): int
    {
        return PasswordPolicy::MIN_LENGTH;
    }
}
