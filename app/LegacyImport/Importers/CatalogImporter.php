<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\ClimbingCatalogBootstrap;
use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;

final class CatalogImporter implements Importer
{
    public function __construct(private ClimbingCatalogBootstrap $bootstrap) {}

    public function key(): string
    {
        return 'catalog';
    }

    public function label(): string
    {
        return 'Climbing catalog';
    }

    public function run(ImportContext $context): ImportResult
    {
        if ($context->dryRun) {
            $context->info('Would seed Climbing catalog (activities, audiences, locks, team).');

            return new ImportResult(processed: 1);
        }

        $this->bootstrap->run();

        return new ImportResult(processed: 1, messages: ['Climbing catalog ready.']);
    }
}
