<?php

namespace App\Service\TenantImport;

use Symfony\Component\HttpFoundation\File\File;

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
    /** Logos sent from the super admin panel: they take the place of the URLs */
    public ?File $logoFile = null;
    public ?File $darkLogoFile = null;
    public string $adminName = '';
    public string $adminEmail = '';
    /** WhatsApp number shown on the site, e.g. "(19) 99999-0000" */
    public ?string $phone = null;
    public ?string $whatsappLink = null;

    /** @var list<string> Problems that prevent this row from being imported */
    public array $errors = [];

    /** The tenant and its admin are already in the database: the row is skipped (unless the e-mail is resent) */
    public bool $alreadyImported = false;

    /** @var list<string> Problems that were worked around */
    public array $warnings = [];

    /** @param int $line Line in the spreadsheet (the header is line 1) */
    public function __construct(public readonly int $line) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
