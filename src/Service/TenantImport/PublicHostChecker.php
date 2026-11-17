<?php

namespace App\Service\TenantImport;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decide se a importação pode baixar de um endereço.
 *
 * O endereço da logo vem do CSV, que é um arquivo de fora: sem esta conferência,
 * uma resposta do formulário consegue apontar o servidor para a própria rede
 * interna (o painel do cPanel, um serviço de metadados, outro site da conta).
 * Por isso só endereços públicos são aceitos.
 *
 * Em dev e test a conferência fica desligada, senão o próprio 127.0.0.1 da
 * máquina do desenvolvedor ficaria bloqueado — e os tenants `127.0.0.1` e
 * `localhost` existem no banco.
 */
class PublicHostChecker
{
    /** Hosts resolvidos nesta requisição, para não consultar o DNS duas vezes pelo mesmo nome */
    private array $resolved = [];

    /** @var null|callable(string): list<string> */
    private $resolver;

    /**
     * @param bool          $enabled  false desliga a conferência (dev e test)
     * @param callable|null $resolver Resolvedor de nomes, injetado pelos testes; o padrão usa o DNS do sistema
     */
    public function __construct(
        #[Autowire('%app.tenant_import.check_public_host%')]
        private readonly bool $enabled = true,
        ?callable $resolver = null,
    ) {
        $this->resolver = $resolver;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Devolve null quando o endereço pode ser baixado, ou o motivo da recusa.
     */
    public function rejectionReason(string $url): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            return 'o endereço não é válido';
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return sprintf('o endereço não usa http nem https ("%s")', $parts['scheme']);
        }

        if (!isset($parts['host']) || $parts['host'] === '') {
            return 'o endereço não é válido';
        }

        $host = trim($parts['host'], '[]');
        $ips = $this->addressesOf($host);

        if ($ips === []) {
            return sprintf('o endereço "%s" não foi encontrado no DNS', $host);
        }

        foreach ($ips as $ip) {
            if (!self::isPublic($ip)) {
                return sprintf('o endereço "%s" aponta para a rede interna (%s)', $host, $ip);
            }
        }

        return null;
    }

    public function isAllowed(string $url): bool
    {
        return $this->rejectionReason($url) === null;
    }

    /** @return list<string> */
    private function addressesOf(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (isset($this->resolved[$host])) {
            return $this->resolved[$host];
        }

        return $this->resolved[$host] = $this->resolver !== null
            ? array_values(($this->resolver)($host))
            : self::systemAddresses($host);
    }

    /** @return list<string> */
    private static function systemAddresses(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        // AAAA: um host só com IPv6 não pode escapar da conferência
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Público = fora das faixas privadas (10/8, 172.16/12, 192.168/16, fc00::/7, fe80::/10)
     * e reservadas (0/8, 127/8, 169.254/16, 240/4, ::1), que é o que estes dois filtros cobrem.
     */
    private static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
