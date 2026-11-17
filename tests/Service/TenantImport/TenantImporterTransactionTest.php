<?php

namespace App\Tests\Service\TenantImport;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use App\Service\TenantImport\PublicHostChecker;
use App\Service\TenantImport\TenantImporter;
use App\Service\TenantImport\TenantImportRow;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * O lote inteiro grava de uma vez: uma falha no meio não pode deixar NEPEs criados,
 * e nenhum e-mail de acesso pode sair antes do commit.
 *
 * Sem banco: o EntityManager é um dublê e `wrapInTransaction` é simulado.
 */
class TenantImporterTransactionTest extends TestCase
{
    /** @var list<string> */
    private array $persistidos = [];

    private function linha(int $line, string $nome, string $dominio, string $email): TenantImportRow
    {
        $row = new TenantImportRow($line);
        $row->tenantName = $nome;
        $row->domain = $dominio;
        $row->adminName = 'Administrador ' . $line;
        $row->adminEmail = $email;

        return $row;
    }

    /**
     * @param \Closure|null $aoGravar executado no flush; lançar daqui simula a falha de gravação
     */
    private function importer(?\Closure $aoGravar, PasswordResetMailer $mailer): TenantImporter
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->method('persist')->willReturnCallback(function (object $entidade): void {
            if ($entidade instanceof Tenant) {
                $this->persistidos[] = $entidade->getDomain();
            }
        });

        $em->method('flush')->willReturnCallback(static function () use ($aoGravar): void {
            if ($aoGravar !== null) {
                ($aoGravar)();
            }
        });

        // wrapInTransaction real o bastante: roda o callback e deixa a exceção subir
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $func) => $func($em));

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');

        return new TenantImporter(
            $this->createStub(TenantRepository::class),
            $this->createStub(UserRepository::class),
            $em,
            $hasher,
            $mailer,
            new MockHttpClient(),
            new PublicHostChecker(false),
            $this->createStub(StorageInterface::class),
        );
    }

    private function mailerQueContaEnvios(array &$enviados): PasswordResetMailer
    {
        $mailer = $this->createMock(PasswordResetMailer::class);
        $mailer->method('send')->willReturnCallback(static function (User $user) use (&$enviados): void {
            $enviados[] = (string) $user->getEmail();
        });

        return $mailer;
    }

    public function testGravaOLoteInteiroQuandoTudoDaCerto(): void
    {
        $enviados = [];
        $importer = $this->importer(null, $this->mailerQueContaEnvios($enviados));

        $result = $importer->import([
            $this->linha(2, 'NEPE Um', 'um.nepebrasil.org', 'um@exemplo.org'),
            $this->linha(3, 'NEPE Dois', 'dois.nepebrasil.org', 'dois@exemplo.org'),
        ], [], 'https://%s');

        $this->assertSame(2, $result->created);
        $this->assertFalse($result->aborted);
        $this->assertSame(['um.nepebrasil.org', 'dois.nepebrasil.org'], $this->persistidos);
        $this->assertSame(['um@exemplo.org', 'dois@exemplo.org'], $enviados);
    }

    /** O caso que motivou a mudança: antes, as linhas anteriores ficavam gravadas */
    public function testFalhaNoMeioNaoDeixaNenhumNepeCriado(): void
    {
        $enviados = [];
        $importer = $this->importer(
            static fn () => throw new \RuntimeException('Duplicate entry'),
            $this->mailerQueContaEnvios($enviados),
        );

        $result = $importer->import([
            $this->linha(2, 'NEPE Um', 'um.nepebrasil.org', 'um@exemplo.org'),
            $this->linha(3, 'NEPE Dois', 'dois.nepebrasil.org', 'dois@exemplo.org'),
            $this->linha(4, 'NEPE Três', 'tres.nepebrasil.org', 'tres@exemplo.org'),
            $this->linha(5, 'NEPE Quatro', 'quatro.nepebrasil.org', 'quatro@exemplo.org'),
            $this->linha(6, 'NEPE Cinco', 'cinco.nepebrasil.org', 'cinco@exemplo.org'),
        ], [], 'https://%s');

        $this->assertSame(0, $result->created, 'nenhum NEPE do lote pode ficar criado');
        $this->assertTrue($result->aborted);

        $mensagens = implode(' ', $result->problemMessages(withLine: false));
        $this->assertStringContainsString('Duplicate entry', $mensagens);
        $this->assertStringContainsString('Nenhum NEPE deste arquivo foi criado', $mensagens);
    }

    public function testNenhumEmailSaiQuandoAGravacaoFalha(): void
    {
        $enviados = [];
        $importer = $this->importer(
            static fn () => throw new \RuntimeException('falhou'),
            $this->mailerQueContaEnvios($enviados),
        );

        $importer->import([
            $this->linha(2, 'NEPE Um', 'um.nepebrasil.org', 'um@exemplo.org'),
            $this->linha(3, 'NEPE Dois', 'dois.nepebrasil.org', 'dois@exemplo.org'),
        ], [], 'https://%s');

        $this->assertSame([], $enviados, 'o convite não pode sair para um NEPE que o rollback apagou');
    }

    public function testApagaAsLogosGravadasQuandoATransacaoCai(): void
    {
        $logo = tempnam(sys_get_temp_dir(), 'logo-gravada-');
        file_put_contents($logo, 'conteudo');

        $storage = $this->createStub(StorageInterface::class);
        $storage->method('resolvePath')->willReturnCallback(
            static fn (object $obj, string $campo): ?string => $campo === 'logoFile' ? $logo : null,
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \RuntimeException('falhou'));
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $func) => $func($em));

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');

        $importer = new TenantImporter(
            $this->createStub(TenantRepository::class),
            $this->createStub(UserRepository::class),
            $em,
            $hasher,
            $this->createStub(PasswordResetMailer::class),
            new MockHttpClient(),
            new PublicHostChecker(false),
            $storage,
        );

        $importer->import([$this->linha(2, 'NEPE Um', 'um.nepebrasil.org', 'um@exemplo.org')], [], null);

        $this->assertFileDoesNotExist($logo, 'a logo copiada pelo Vich não pode ficar órfã em public/uploads');
    }

    public function testReenvioDeEmailContinuaFuncionandoSemLinhasNovas(): void
    {
        $enviados = [];
        $importer = $this->importer(null, $this->mailerQueContaEnvios($enviados));

        $existente = (new User())->setEmail('ja@exemplo.org')->setUsername('ja@exemplo.org');
        $existente->setTenant((new Tenant())->setDomain('ja.nepebrasil.org'));

        $row = $this->linha(2, 'NEPE Já', 'ja.nepebrasil.org', 'ja@exemplo.org');
        $result = $importer->import([$row], [2 => $existente], 'https://%s');

        $this->assertSame(0, $result->created);
        $this->assertSame(['ja@exemplo.org'], $enviados);
    }
}
