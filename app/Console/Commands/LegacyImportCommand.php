<?php

namespace App\Console\Commands;

use App\LegacyImport\ImportContext;
use App\LegacyImport\LegacyImportRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('legacy:import {--only=* : Importer keys to run (default: core set)} {--dry-run : Report without writing} {--documents-path= : Path to legacy upload root containing document files}')]
#[Description('Import Fox Coverts climbing-team data from the legacy database connection')]
class LegacyImportCommand extends Command
{
    public function handle(LegacyImportRegistry $registry): int
    {
        $only = array_values(array_filter($this->option('only') ?? []));
        $dryRun = (bool) $this->option('dry-run');
        $documentsPath = $this->option('documents-path');

        try {
            DB::connection('legacy')->getPdo();
        } catch (Throwable $exception) {
            $this->error('Cannot connect to the legacy database. Set LEGACY_DB_* in the environment.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $context = new ImportContext(
            legacyConnection: 'legacy',
            dryRun: $dryRun,
            documentsSourcePath: is_string($documentsPath) && $documentsPath !== '' ? $documentsPath : null,
            output: $this->output,
        );

        $importers = $registry->resolve($only === [] ? null : $only);

        foreach ($importers as $importer) {
            $this->info(($dryRun ? '[dry-run] ' : '').$importer->label().'…');

            try {
                $result = $importer->run($context);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line("  processed={$result->processed} skipped={$result->skipped}");

            foreach ($result->messages as $message) {
                $this->line('  · '.$message);
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Legacy import complete.');

        return self::SUCCESS;
    }
}
