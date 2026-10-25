<?php

namespace App\Console\Commands;

use App\Qualifications\CatalogueCompiler;
use App\Qualifications\QualificationCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('qualifications:cache')]
#[Description('Compile qualification catalogues into a PHP snapshot')]
class CacheQualificationsCommand extends Command
{
    public function handle(CatalogueCompiler $compiler, QualificationCatalogue $catalogue): int
    {
        $path = $compiler->write();
        $catalogue->refresh();

        $this->components->info("Qualification catalogue cached to [{$path}].");

        return self::SUCCESS;
    }
}
