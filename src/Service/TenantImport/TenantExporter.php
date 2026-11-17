<?php

namespace App\Service\TenantImport;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Service\TenantUrl;
use League\Csv\Writer;

/**
 * Tira os NEPEs do sistema num CSV que a própria importação lê de volta.
 *
 * O que garante a ida e volta é o cabeçalho: o parser casa as colunas pelo início
 * do título, então cada título aqui precisa começar pelo prefixo que ele espera.
 * TenantExporterTest confere isso contra TenantImportParser::COLUMNS, para que uma
 * mudança no formulário não quebre a volta em silêncio.
 */
class TenantExporter
{
    /**
     * Campo da importação => título da coluna no arquivo exportado.
     * A ordem é a ordem das colunas; os títulos seguem os da planilha do Forms.
     */
    public const COLUMN_TITLES = [
        'tenantName'     => 'Nome da instituição',
        'domain'         => 'Domínio do site',
        'theme'          => 'Tema visual',
        'primaryColor'   => 'Cor principal (#RRGGBB)',
        'secondaryColor' => 'Cor secundária',
        'logo'           => 'Logo (fundo claro)',
        'darkLogo'       => 'Logo (fundo escuro)',
        'adminName'      => 'Nome completo',
        'adminEmail'     => 'E-mail',
        'whatsapp'       => 'WhatsApp',
    ];

    /** Caminho público das logos, igual ao uri_prefix do mapping tenant_logo */
    private const LOGO_URI_PREFIX = '/uploads/tenant/logo/';

    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly UserRepository $users,
    ) {}

    public function filename(?\DateTimeImmutable $hoje = null): string
    {
        return sprintf('nepes-%s.csv', ($hoje ?? new \DateTimeImmutable())->format('Y-m-d'));
    }

    /**
     * O CSV inteiro, em UTF-8 sem BOM e separado por vírgula.
     *
     * Sem BOM de propósito: ele ficaria colado no primeiro título e atrapalharia
     * quem lê o arquivo com outra ferramenta.
     */
    public function toCsv(): string
    {
        $writer = Writer::fromString();
        $writer->insertOne(array_values(self::COLUMN_TITLES));

        foreach ($this->rows() as $row) {
            $writer->insertOne($row);
        }

        return $writer->toString();
    }

    /**
     * Uma linha por NEPE, na mesma ordem da listagem do painel.
     *
     * @return list<list<string>>
     */
    public function rows(): array
    {
        $admins = $this->adminsByTenant();
        $rows = [];

        foreach ($this->tenants->findBy([], ['name' => 'ASC']) as $tenant) {
            $admin = $admins[$tenant->getId()] ?? null;

            $rows[] = [
                $tenant->getName(),
                $tenant->getDomain(),
                $tenant->getTheme(),
                (string) $tenant->getPrimaryColor(),
                (string) $tenant->getSecondaryColor(),
                $this->logoUrl($tenant, $tenant->getLogo()),
                $this->logoUrl($tenant, $tenant->getDarkLogo()),
                $admin?->getName() ?? '',
                (string) ($admin?->getEmail() ?? ''),
                (string) ($tenant->getPhone() ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * O administrador mais antigo de cada NEPE: a importação cria um por linha,
     * então é ele que volta no arquivo quando o NEPE tem mais de um.
     *
     * @return array<int, User>
     */
    private function adminsByTenant(): array
    {
        $admins = [];

        foreach ($this->users->findBy(['workGroup' => 0], ['id' => 'ASC']) as $user) {
            $tenantId = $user->getTenant()?->getId();

            if ($tenantId !== null && !isset($admins[$tenantId])) {
                $admins[$tenantId] = $user;
            }
        }

        return $admins;
    }

    /** Endereço público da logo no site do próprio NEPE, para a importação conseguir baixá-la */
    private function logoUrl(Tenant $tenant, ?string $arquivo): string
    {
        if ($arquivo === null || $arquivo === '') {
            return '';
        }

        return TenantUrl::base($tenant) . self::LOGO_URI_PREFIX . $arquivo;
    }
}
