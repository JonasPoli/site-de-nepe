<?php

namespace App\Service\TenantImport;

/**
 * One answer of the Google Forms spreadsheet (a tenant and its admin), already normalized.
 */
final class TenantImportRow
{
    public string $tenantName = '';
    public string $domain = '';
    public string $theme = 'nepe';
    public ?string $primaryColor = null;
    public ?string $secondaryColor = null;
    public ?string $logoUrl = null;
    public ?string $darkLogoUrl = null;
    public string $adminName = '';
    public string $adminEmail = '';

    /** @var list<string> Problems that prevent this row from being imported */
    public array $errors = [];

    /** @var list<string> Problems that were worked around */
    public array $warnings = [];

    /** @param int $line Line in the spreadsheet (the header is line 1) */
    public function __construct(public readonly int $line) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
