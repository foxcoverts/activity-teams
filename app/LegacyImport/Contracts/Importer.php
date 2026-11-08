<?php

namespace App\LegacyImport\Contracts;

use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;

interface Importer
{
    public function key(): string;

    public function label(): string;

    public function run(ImportContext $context): ImportResult;
}
