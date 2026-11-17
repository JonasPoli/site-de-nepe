<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\BibliaService;
use PHPUnit\Framework\TestCase;

/**
 * Formato do `author` na API /api/biblia (spec api-biblia, "Foto e WhatsApp do autor na API").
 *
 * O método é privado e o serviço precisa do Doctrine para o resto: aqui ele é chamado por
 * reflexão com usuários em memória, sem banco.
 */
class BibliaAuthorFormatTest extends TestCase
{
    private \ReflectionMethod $formatAuthor;
    private BibliaService $service;

    protected function setUp(): void
    {
        $classe = new \ReflectionClass(BibliaService::class);
        $this->service = $classe->newInstanceWithoutConstructor();
        $this->formatAuthor = $classe->getMethod('formatAuthor');
        $this->formatAuthor->setAccessible(true);
    }

    /** @return array<string, mixed>|null */
    private function format(?User $author, string $baseUrl = 'https://nepealcione.nepebrasil.org'): ?array
    {
        return $this->formatAuthor->invoke($this->service, $author, $baseUrl);
    }

    public function testAuthorWithPhotoAndWhatsapp(): void
    {
        $author = (new User())
            ->setName('Editora do NEPE')
            ->setPhoto('editora-abc123.jpg')
            ->setWhatsapp('(16) 99999-0000');

        $this->assertSame([
            'name'     => 'Editora do NEPE',
            'photoUrl' => 'https://nepealcione.nepebrasil.org/uploads/user_photo/editora-abc123.jpg',
            'whatsapp' => '(16) 99999-0000',
        ], $this->format($author));
    }

    public function testAuthorWithoutPhotoOrWhatsapp(): void
    {
        $formatted = $this->format((new User())->setName('Revisor do NEPE'));

        $this->assertSame('Revisor do NEPE', $formatted['name']);
        $this->assertNull($formatted['photoUrl']);
        $this->assertNull($formatted['whatsapp']);
    }

    public function testContentWithoutAuthor(): void
    {
        $this->assertNull($this->format(null));
    }

    public function testAuthorWithoutNameIsTreatedAsNoAuthor(): void
    {
        $this->assertNull($this->format(new User()));
    }

    public function testPhotoUrlUsesTheDomainOfTheTenantThatPublished(): void
    {
        $author = (new User())->setName('Editora')->setPhoto('foto.png');

        $formatted = $this->format($author, 'http://localhost');

        $this->assertSame('http://localhost/uploads/user_photo/foto.png', $formatted['photoUrl']);
    }

    public function testLoginDataNeverLeavesInTheApi(): void
    {
        $author = (new User())
            ->setName('Editora')
            ->setUsername('editora')
            ->setEmail('editora@nepebrasil.org')
            ->setPassword('$2y$hash');

        $formatted = $this->format($author);

        $this->assertSame(['name', 'photoUrl', 'whatsapp'], array_keys($formatted));
    }
}
