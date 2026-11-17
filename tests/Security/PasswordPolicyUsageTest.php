<?php

namespace App\Tests\Security;

use App\Controller\ResetPasswordController;
use App\Controller\admin\AdminContentController;
use App\Controller\admin\ProfileController;
use App\Controller\superadmin\SuperAdminController;
use App\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

/**
 * A política vale nos quatro pontos que definem senha. Os controllers não têm banco nos testes:
 * o que se confere aqui é que cada um passa pela PasswordPolicy e que nenhum ponto voltou a
 * usar um mínimo próprio — senha forte só no perfil seria contornável pelos outros caminhos.
 */
class PasswordPolicyUsageTest extends TestCase
{
    /** @return iterable<string, array{class-string}> */
    public static function pontosDeSenha(): iterable
    {
        yield 'perfil do usuário' => [ProfileController::class];
        yield 'novo usuário do NEPE' => [AdminContentController::class];
        yield 'usuário pelo super admin' => [SuperAdminController::class];
        yield 'redefinição por e-mail' => [ResetPasswordController::class];
    }

    /** @dataProvider pontosDeSenha */
    public function testControllerUsesThePolicy(string $controller): void
    {
        $code = self::codigo($controller);

        $this->assertStringContainsString('PasswordPolicy', $code);
    }

    /** @dataProvider pontosDeSenha */
    public function testControllerDoesNotKeepItsOwnMinimumLength(string $controller): void
    {
        $code = self::codigo($controller);

        $this->assertStringNotContainsString('MIN_PASSWORD_LENGTH', $code);
        $this->assertDoesNotMatchRegularExpression('/mb_strlen\(\$password\)\s*<\s*\d/', $code);
    }

    /** @dataProvider pontosDeSenha */
    public function testControllerComparesTheTwoPasswordFields(string $controller): void
    {
        $code = self::codigo($controller);

        $this->assertStringContainsString('password_confirmation', $code);
    }

    public function testRejectedPasswordsAreTheSameEverywhere(): void
    {
        // As senhas que o painel aceitava antes (8 caracteres, sem maiúscula ou sem especial)
        foreach (['editor123', 'senha123', 'nepe2026', 'NEPE2026', 'Nepe2026'] as $antiga) {
            $this->assertFalse(PasswordPolicy::isSatisfiedBy($antiga), $antiga);
        }
    }

    /** @param class-string $controller */
    private static function codigo(string $controller): string
    {
        return (string) file_get_contents((string) (new \ReflectionClass($controller))->getFileName());
    }
}
