<?php

namespace App\Tests\Security;

use App\Security\ProfilePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Regras do /admin/perfil: o que o usuário muda em si mesmo, o que o perfil nunca grava
 * e quando é preciso entrar de novo no sistema.
 */
class ProfileUpdatePolicyTest extends TestCase
{
    public function testProfileEditsOnlyItsOwnFields(): void
    {
        foreach (['name', 'email', 'username', 'whatsapp', 'photo', 'password'] as $field) {
            $this->assertTrue(ProfilePolicy::isEditable($field), $field);
        }
    }

    public function testRoleAndTenantAreNeverEditedByTheProfile(): void
    {
        foreach (ProfilePolicy::IGNORED_FIELDS as $field) {
            $this->assertFalse(ProfilePolicy::isEditable($field), $field);
        }

        $this->assertContains('workGroup', ProfilePolicy::IGNORED_FIELDS);
        $this->assertContains('tenant', ProfilePolicy::IGNORED_FIELDS);
    }

    public function testNewLoginIsRequiredWhenThePasswordChanges(): void
    {
        $this->assertTrue(ProfilePolicy::requiresNewLogin('editora', 'editora', true));
    }

    public function testNewLoginIsRequiredWhenTheUsernameChanges(): void
    {
        $this->assertTrue(ProfilePolicy::requiresNewLogin('editora', 'editora.nepe', false));
    }

    public function testSessionIsKeptWhenOnlyTheOtherFieldsChange(): void
    {
        $this->assertFalse(ProfilePolicy::requiresNewLogin('editora', 'editora', false));
        // Espaço nas pontas não é mudança de usuário
        $this->assertFalse(ProfilePolicy::requiresNewLogin('editora', ' editora ', false));
    }

    public function testOptionalFieldsBecomeNullWhenLeftBlank(): void
    {
        $this->assertNull(ProfilePolicy::normalizeOptional(''));
        $this->assertNull(ProfilePolicy::normalizeOptional('   '));
        $this->assertNull(ProfilePolicy::normalizeOptional(null));
        $this->assertSame('editora@nepebrasil.org', ProfilePolicy::normalizeOptional('  editora@nepebrasil.org '));
    }

    public function testWhatsappKeepsTheFormatTypedWithinTheLimit(): void
    {
        $this->assertSame('(16) 99999-0000', ProfilePolicy::normalizeWhatsapp(' (16)  99999-0000 '));
        $this->assertNull(ProfilePolicy::normalizeWhatsapp(' '));
        $this->assertSame(
            ProfilePolicy::WHATSAPP_MAX_LENGTH,
            mb_strlen((string) ProfilePolicy::normalizeWhatsapp(str_repeat('9', 60)))
        );
    }

    public function testPhotoAcceptsOnlyImagesWithinTheSizeLimit(): void
    {
        $this->assertNull(ProfilePolicy::photoError('image/jpeg', 1024 * 1024));
        $this->assertNull(ProfilePolicy::photoError('image/png', 10));
        $this->assertNull(ProfilePolicy::photoError('image/webp', ProfilePolicy::MAX_PHOTO_BYTES));
    }

    public function testPhotoRefusesOtherFileTypes(): void
    {
        $this->assertStringContainsString('JPEG', (string) ProfilePolicy::photoError('application/pdf', 1000));
        $this->assertNotNull(ProfilePolicy::photoError('image/svg+xml', 1000));
        $this->assertNotNull(ProfilePolicy::photoError(null, 1000));
    }

    public function testPhotoRefusesFilesOverFourMegabytes(): void
    {
        $error = ProfilePolicy::photoError('image/jpeg', ProfilePolicy::MAX_PHOTO_BYTES + 1);

        $this->assertStringContainsString('4 MB', (string) $error);
    }
}
