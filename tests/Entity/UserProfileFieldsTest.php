<?php

namespace App\Tests\Entity;

use App\Entity\Tenant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Campos do perfil (foto e WhatsApp) e o ciclo de serialização do usuário logado,
 * que vai para a sessão a cada requisição.
 */
class UserProfileFieldsTest extends TestCase
{
    public function testWhatsappAndPhotoStartEmpty(): void
    {
        $user = new User();

        $this->assertNull($user->getWhatsapp());
        $this->assertNull($user->getPhoto());
        $this->assertNull($user->getPhotoFile());
        $this->assertNull($user->getPhotoUpdatedAt());
    }

    public function testSettersKeepTheValues(): void
    {
        $user = (new User())->setWhatsapp('(16) 99999-0000')->setPhoto('foto.jpg');

        $this->assertSame('(16) 99999-0000', $user->getWhatsapp());
        $this->assertSame('foto.jpg', $user->getPhoto());
    }

    public function testPhotoFileMarksTheEntityAsChanged(): void
    {
        $file = new File(__FILE__);

        $user = (new User())->setPhotoFile($file);

        $this->assertSame($file, $user->getPhotoFile());
        $this->assertNotNull($user->getPhotoUpdatedAt(), 'sem campo alterado o Vich não grava o arquivo novo');
    }

    public function testSerializationKeepsEveryProfileFieldAndDropsThePhotoFile(): void
    {
        $user = (new User())
            ->setUsername('editora')
            ->setName('Editora do NEPE')
            ->setEmail('editora@nepebrasil.org')
            ->setWhatsapp('(16) 99999-0000')
            ->setPhoto('foto.jpg')
            ->setWorkGroup(1)
            ->setTenant((new Tenant())->setDomain('nepealcione.nepebrasil.org'))
            ->setPassword('$2y$hash');
        $user->setPhotoFile(new File(__FILE__));

        /** @var User $restored */
        $restored = unserialize(serialize($user));

        $this->assertSame('editora', $restored->getUsername());
        $this->assertSame('Editora do NEPE', $restored->getName());
        $this->assertSame('editora@nepebrasil.org', $restored->getEmail());
        $this->assertSame('(16) 99999-0000', $restored->getWhatsapp());
        $this->assertSame('foto.jpg', $restored->getPhoto());
        $this->assertSame(1, $restored->getWorkGroup());
        $this->assertSame('nepealcione.nepebrasil.org', $restored->getTenant()?->getDomain());
        $this->assertSame('$2y$hash', $restored->getPassword());
        $this->assertNotNull($restored->getPhotoUpdatedAt());
        $this->assertNull($restored->getPhotoFile(), 'o File não é serializável e não volta da sessão');
    }

    public function testRolesSurviveSerialization(): void
    {
        $user = (new User())->setWorkGroup(0)->setTenant(new Tenant());

        /** @var User $restored */
        $restored = unserialize(serialize($user));

        $this->assertContains('ROLE_NEPE_ADMIN', $restored->getRoles());
        $this->assertNotContains('ROLE_SUPER_ADMIN', $restored->getRoles());
    }

    public function testOldSessionWithoutTheNewFieldsStillRestores(): void
    {
        // Sessão gravada antes desta mudança: o array não tem whatsapp, photo nem photoUpdatedAt
        $user = new User();
        $user->__unserialize([
            'id'        => 7,
            'username'  => 'antigo',
            'name'      => 'Usuário antigo',
            'email'     => null,
            'workGroup' => 1,
            'tenant'    => null,
            'roles'     => [],
            'password'  => '$2y$hash',
        ]);

        $this->assertSame('antigo', $user->getUsername());
        $this->assertNull($user->getWhatsapp());
        $this->assertNull($user->getPhoto());
    }
}
