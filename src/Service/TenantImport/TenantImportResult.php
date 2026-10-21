<?php

namespace App\Service\TenantImport;

/**
 * What TenantImporter::import() did, for app:tenant:import and the super admin panel.
 */
final class TenantImportResult
{
    public int $created = 0;
    public int $emailed = 0;
    public int $emailFailures = 0;

    /** A database error stopped the import: the rows after it weren't processed */
    public bool $aborted = false;

    /** @var list<array{line: int, message: string}> Things the user must check, e.g. logos that couldn't be downloaded */
    public array $problems = [];

    public function addProblem(int $line, string $message): void
    {
        $this->problems[] = ['line' => $line, 'message' => $message];
    }

    /** @return list<string> */
    public function problemMessages(bool $withLine = true): array
    {
        return array_map(
            static fn (array $problem): string => $withLine ? sprintf('Linha %d: %s', $problem['line'], $problem['message']) : ucfirst($problem['message']),
            $this->problems,
        );
    }

    public function isSuccessful(): bool
    {
        return !$this->aborted && $this->emailFailures === 0;
    }
}
