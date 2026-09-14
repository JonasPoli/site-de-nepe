<?php

namespace App\Service\TenantImport;

use League\Csv\Exception as CsvException;
use League\Csv\Info;
use League\Csv\Reader;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\UnicodeString;

/**
 * Reads the CSV exported from the Google Forms answers spreadsheet and turns
 * each answer into a normalized TenantImportRow.
 *
 * It never touches the database: conflicts with tenants and users that already
 * exist are checked by app:tenant:import.
 */
class TenantImportParser
{
    /**
     * Field => accepted beginnings of the column header, compared lowercase and without accents.
     * Google Forms uses the question title as header, so "Domínio do site (ex.: …)" matches "dominio".
     * When a field lists alternatives, the first one present in the file wins.
     */
    public const COLUMNS = [
        'tenantName'     => ['nome da instituicao'],
        'domain'         => ['dominio'],
        'theme'          => ['tema'],
        'primaryColor'   => ['cor principal'],
        'secondaryColor' => ['cor secundaria'],
        'logo'           => ['logo (fundo claro)'],
        'darkLogo'       => ['logo (fundo escuro)'],
        'adminName'      => ['nome completo'],
        'adminEmail'     => ['e-mail', 'email', 'endereco de e-mail'],
        'consent'        => ['autorizo'],
    ];

    private const REQUIRED_COLUMNS = ['tenantName', 'adminName', 'adminEmail'];

    /**
     * @param string|null $baseDomain When set, answers without a domain get "<name-slug>.<baseDomain>"
     *
     * @return list<TenantImportRow>
     *
     * @throws \InvalidArgumentException when the file can't be read or required columns are missing
     */
    public function parseFile(string $path, ?string $baseDomain = null): array
    {
        $header = null;
        $map = [];
        $rows = [];

        try {
            $csv = Reader::from($path, 'r');

            // Google Sheets exports with ",", Excel in pt-BR saves with ";"
            $stats = Info::getDelimiterStats($csv, [',', ';', "\t"], 1);
            arsort($stats);
            $csv->setDelimiter((string) array_key_first($stats));

            foreach ($csv->getRecords() as $offset => $record) {
                if ($header === null) {
                    $header = $record;
                    $map = $this->mapColumns($header, $baseDomain === null);
                    continue;
                }

                if (trim(implode('', $record)) === '') {
                    continue;
                }

                // Offset 0 is the header, which is line 1 of the spreadsheet
                $rows[] = $this->parseRecord($offset + 1, $record, $map, $baseDomain);
            }
        } catch (CsvException $e) {
            throw new \InvalidArgumentException(sprintf('Não foi possível ler o CSV: %s', $e->getMessage()), 0, $e);
        }

        if ($header === null) {
            throw new \InvalidArgumentException('O arquivo CSV está vazio.');
        }

        $this->markDuplicates($rows);

        return $rows;
    }

    public static function normalizeDomain(string $value): ?string
    {
        $domain = mb_strtolower(trim($value));
        $domain = preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $domain); // scheme
        $domain = preg_replace('~[/?#].*$~', '', $domain);              // path, query, fragment
        $domain = preg_replace('~:\d+$~', '', $domain);                  // port
        $domain = rtrim($domain, '.');

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return preg_match('~^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$~', $domain) ? $domain : null;
    }

    public static function normalizeColor(string $value): ?string
    {
        if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value), $matches)) {
            return null;
        }

        $hex = strtolower($matches[1]);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . $hex;
    }

    /** Turns a Google Drive link into a direct download link, or null when it isn't a Drive link */
    public static function driveDownloadUrl(string $url): ?string
    {
        if (!preg_match('~^https://drive\.google\.com/(?:open\?(?:[^#]*&)?id=|file/d/|uc\?(?:[^#]*&)?id=)([\w-]+)~', $url, $matches)) {
            return null;
        }

        return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
    }

    public static function normalizeLabel(string $value): string
    {
        return (new UnicodeString($value))->ascii()->lower()->collapseWhitespace()->toString();
    }

    /**
     * @param list<string|null> $header
     *
     * @return array<string, int> field => column index
     */
    private function mapColumns(array $header, bool $domainRequired): array
    {
        $labels = array_map(static fn (?string $label): string => self::normalizeLabel((string) $label), $header);
        $map = [];

        foreach (self::COLUMNS as $field => $prefixes) {
            foreach ($prefixes as $prefix) {
                $matches = array_keys(array_filter($labels, static fn (string $label): bool => str_starts_with($label, $prefix)));

                if (count($matches) > 1) {
                    throw new \InvalidArgumentException(sprintf(
                        'Mais de uma coluna começa com "%s": %s. Renomeie as perguntas para não haver ambiguidade.',
                        $prefix,
                        implode(', ', array_map(static fn (int $index): string => '"' . $header[$index] . '"', $matches)),
                    ));
                }

                if (count($matches) === 1) {
                    $map[$field] = $matches[0];
                    break;
                }
            }
        }

        $required = $domainRequired ? [...self::REQUIRED_COLUMNS, 'domain'] : self::REQUIRED_COLUMNS;
        $missing = array_diff($required, array_keys($map));

        if ($missing !== []) {
            throw new \InvalidArgumentException(sprintf(
                "Colunas obrigatórias não encontradas: %s.\nCabeçalhos do arquivo: %s",
                implode(', ', array_map(static fn (string $field): string => '"' . self::COLUMNS[$field][0] . '…"', $missing)),
                implode(' | ', $header),
            ));
        }

        return $map;
    }

    /**
     * @param list<string|null>  $record
     * @param array<string, int> $map
     */
    private function parseRecord(int $line, array $record, array $map, ?string $baseDomain): TenantImportRow
    {
        $value = static fn (string $field): string => isset($map[$field])
            ? (new UnicodeString((string) ($record[$map[$field]] ?? '')))->collapseWhitespace()->toString()
            : '';

        $row = new TenantImportRow($line);

        $row->tenantName = $value('tenantName');
        if ($row->tenantName === '') {
            $row->errors[] = 'Nome da instituição não informado.';
        } elseif (mb_strlen($row->tenantName) > 255) {
            $row->errors[] = 'Nome da instituição com mais de 255 caracteres.';
        }

        $this->parseDomain($row, $value('domain'), $baseDomain);
        $this->parseTheme($row, $value('theme'));
        $row->primaryColor = $this->parseColor($row, $value('primaryColor'), 'Cor principal');
        $row->secondaryColor = $this->parseColor($row, $value('secondaryColor'), 'Cor secundária');
        $row->logoUrl = $this->parseFileUrl($row, $value('logo'), 'Logo (fundo claro)');
        $row->darkLogoUrl = $this->parseFileUrl($row, $value('darkLogo'), 'Logo (fundo escuro)');

        $row->adminName = $value('adminName');
        if ($row->adminName === '') {
            $row->errors[] = 'Nome do administrador não informado.';
        } elseif (mb_strlen($row->adminName) > 255) {
            $row->errors[] = 'Nome do administrador com mais de 255 caracteres.';
        }

        $row->adminEmail = mb_strtolower($value('adminEmail'));
        if ($row->adminEmail === '') {
            $row->errors[] = 'E-mail do administrador não informado.';
        } elseif (!filter_var($row->adminEmail, FILTER_VALIDATE_EMAIL) || strlen($row->adminEmail) > 180) {
            $row->errors[] = sprintf('E-mail do administrador inválido: "%s".', $row->adminEmail);
        }

        if (isset($map['consent']) && $value('consent') === '') {
            $row->errors[] = 'Sem autorização para uso dos dados (LGPD).';
        }

        return $row;
    }

    private function parseDomain(TenantImportRow $row, string $raw, ?string $baseDomain): void
    {
        if ($raw === '') {
            if ($baseDomain === null) {
                $row->errors[] = 'Domínio não informado (use --base-domain para gerar um subdomínio).';
                return;
            }

            $slug = trim(substr((new AsciiSlugger())->slug($row->tenantName)->lower()->toString(), 0, 63), '-');
            if ($slug === '') {
                $row->errors[] = 'Domínio não informado.';
                return;
            }

            $row->domain = $slug . '.' . $baseDomain;
            $row->warnings[] = sprintf('Sem domínio: usando %s.', $row->domain);
            return;
        }

        $domain = self::normalizeDomain($raw);
        if ($domain === null) {
            $row->errors[] = sprintf('Domínio inválido: "%s".', $raw);
            return;
        }

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*://)?www\.~i', $raw)) {
            $row->warnings[] = 'O "www." foi removido do domínio.';
        }

        $row->domain = $domain;
    }

    private function parseTheme(TenantImportRow $row, string $raw): void
    {
        $theme = self::normalizeLabel($raw);

        if (str_starts_with($theme, 'moderno')) {
            $row->theme = 'moderno';
        } elseif ($theme !== '' && !str_starts_with($theme, 'nepe')) {
            $row->warnings[] = sprintf('Tema "%s" não reconhecido: usando Nepe.', $raw);
        }
    }

    private function parseColor(TenantImportRow $row, string $raw, string $label): ?string
    {
        if ($raw === '') {
            return null;
        }

        $color = self::normalizeColor($raw);
        if ($color === null) {
            $row->warnings[] = sprintf('%s inválida ("%s"): mantida a cor padrão.', $label, $raw);
        }

        return $color;
    }

    private function parseFileUrl(TenantImportRow $row, string $raw, string $label): ?string
    {
        if ($raw === '') {
            return null;
        }

        // Google Forms separates multiple uploads with ", ": only the first one is used
        $url = trim(explode(', ', $raw)[0]);
        $url = self::driveDownloadUrl($url) ?? $url;

        if (!preg_match('~^https?://~i', $url)) {
            $row->warnings[] = sprintf('%s ignorada: "%s" não é um link.', $label, $raw);
            return null;
        }

        return $url;
    }

    /** @param list<TenantImportRow> $rows */
    private function markDuplicates(array $rows): void
    {
        // Same domain answered more than once: the last valid answer wins (usually a correction)
        $lastByDomain = [];
        foreach ($rows as $row) {
            if ($row->isValid()) {
                $lastByDomain[$row->domain] = $row;
            }
        }
        foreach ($rows as $row) {
            if ($row->isValid() && $lastByDomain[$row->domain] !== $row) {
                $row->errors[] = sprintf('Domínio repetido: substituído pela resposta da linha %d.', $lastByDomain[$row->domain]->line);
            }
        }

        // An e-mail can belong to a single user in the whole system
        $firstByEmail = [];
        foreach ($rows as $row) {
            if (!$row->isValid()) {
                continue;
            }
            if (isset($firstByEmail[$row->adminEmail])) {
                $row->errors[] = sprintf('E-mail já usado pelo administrador da linha %d.', $firstByEmail[$row->adminEmail]->line);
                continue;
            }
            $firstByEmail[$row->adminEmail] = $row;
        }
    }
}
