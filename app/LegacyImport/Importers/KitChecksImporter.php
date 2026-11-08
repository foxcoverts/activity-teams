<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use RuntimeException;

final class KitChecksImporter implements Importer
{
    public function key(): string
    {
        return 'kit_checks';
    }

    public function label(): string
    {
        return 'Kit checks (deferred)';
    }

    public function run(ImportContext $context): ImportResult
    {
        throw new RuntimeException(
            'Kit checks import is deferred until US-23/US-33 ship. Keep the legacy database (or a slim extract) and re-run: php artisan legacy:import --only=kit_checks',
        );
    }
}
