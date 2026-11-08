<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use RuntimeException;

final class TodosImporter implements Importer
{
    public function key(): string
    {
        return 'todos';
    }

    public function label(): string
    {
        return 'Todos (deferred)';
    }

    public function run(ImportContext $context): ImportResult
    {
        throw new RuntimeException(
            'Todos import is deferred until US-22 ships. Keep the legacy database (or a slim extract) and re-run: php artisan legacy:import --only=todos',
        );
    }
}
