<?php

namespace App\Tests\Security;

use App\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Cenários da spec politica-de-senha: "Requisitos de uma senha aceita".
 */
class PasswordPolicyTest extends TestCase
{
    public function testAcceptsPasswordWithAllRequirements(): void
    {
        $this->assertTrue(PasswordPolicy::isSatisfiedBy('Nepe2026!'));
        $this->assertSame([], PasswordPolicy::unmetRequirements('Nepe2026!'));
        $this->assertNull(PasswordPolicy::errorMessage('Nepe2026!'));
    }

    public function testEightCharactersAreNotEnough(): void
    {
        $unmet = PasswordPolicy::unmetRequirements('Nepe26!x');

        $this->assertFalse(PasswordPolicy::isSatisfiedBy('Nepe26!x'));
        $this->assertSame(['Mais de 8 caracteres'], $unmet);
    }

    public function testNineCharactersAreEnough(): void
    {
        $this->assertTrue(PasswordPolicy::isSatisfiedBy('Nepe26!xy'));
    }

    public function testRejectsPasswordWithoutUppercase(): void
    {
        $unmet = PasswordPolicy::unmetRequirements('nepe2026!');

        $this->assertSame(['Uma letra maiúscula'], $unmet);
        $this->assertStringContainsString('maiúscula', (string) PasswordPolicy::errorMessage('nepe2026!'));
    }

    public function testRejectsPasswordWithoutLowercase(): void
    {
        $this->assertSame(['Uma letra minúscula'], PasswordPolicy::unmetRequirements('NEPE2026!'));
    }

    public function testRejectsPasswordWithoutSpecialCharacter(): void
    {
        $unmet = PasswordPolicy::unmetRequirements('Nepe20261');

        $this->assertCount(1, $unmet);
        $this->assertStringContainsString('caractere especial', $unmet[0]);
    }

    public function testSpaceCountsAsSpecialCharacter(): void
    {
        $this->assertTrue(PasswordPolicy::isSatisfiedBy('Nepe 2026 arc'));
    }

    public function testAccentedLetterIsNotASpecialCharacter(): void
    {
        // "Ação" tem só letras e dígitos: o acento não vale como caractere especial
        $unmet = PasswordPolicy::unmetRequirements('Acaoacao12');
        $this->assertCount(1, $unmet);
        $this->assertStringContainsString('caractere especial', $unmet[0]);
    }

    public function testAccentedLettersCountTowardsLength(): void
    {
        // 9 caracteres, sendo 2 acentuados: mb_strlen conta letras, não bytes
        $this->assertTrue(PasswordPolicy::isSatisfiedBy('Açãoabcd!'));
    }

    public function testEmptyPasswordMissesEveryRequirement(): void
    {
        $this->assertCount(4, PasswordPolicy::unmetRequirements(''));
    }

    public function testRejectsPasswordLongerThanTheLimit(): void
    {
        $long = 'Aa!' . str_repeat('x', PasswordPolicy::MAX_LENGTH);

        $this->assertFalse(PasswordPolicy::isSatisfiedBy($long));
        $this->assertSame(['A senha é longa demais'], PasswordPolicy::unmetRequirements($long));
    }

    public function testPasswordAtTheLimitIsAccepted(): void
    {
        $atLimit = 'Aa!' . str_repeat('x', PasswordPolicy::MAX_LENGTH - 3);

        $this->assertSame(PasswordPolicy::MAX_LENGTH, mb_strlen($atLimit));
        $this->assertTrue(PasswordPolicy::isSatisfiedBy($atLimit));
    }

    public function testRequirementsAreShownInScreenOrderWithRegexForTheBrowser(): void
    {
        $requirements = PasswordPolicy::requirements();

        $this->assertCount(4, $requirements);
        $this->assertSame(['length', 'lowercase', 'uppercase', 'special'], array_column($requirements, 'key'));

        foreach ($requirements as $requirement) {
            $this->assertNotSame('', $requirement['label']);
            // A mesma expressão vai para o JavaScript: precisa ser válida como PCRE
            $this->assertNotFalse(@preg_match('/' . $requirement['regex'] . '/u', 'Nepe2026!'));
        }
    }

    public function testEveryRequirementLabelCanAppearAsUnmet(): void
    {
        $labels = array_column(PasswordPolicy::requirements(), 'label');

        $this->assertSame($labels, PasswordPolicy::unmetRequirements(''));
    }
}
