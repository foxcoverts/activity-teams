<?php

namespace App\Console\Commands;

use App\Qualifications\CatalogueCompiler;
use App\Qualifications\QualificationCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('qualifications:clear')]
#[Description('Remove the compiled qualification catalogue snapshot')]
class ClearQualificationsCommand extends Command
{
    public function handle(CatalogueCompiler $compiler, QualificationCatalogue $catalogue): int
    {
        $compiler->clear();
        $catalogue->refresh();

        $this->components->info('Qualification catalogue cache cleared.');

        return self::SUCCESS;
    }
}
