<?php

namespace Database\Seeders;

use App\LegacyImport\ClimbingCatalogBootstrap;
use Illuminate\Database\Seeder;

class ClimbingCatalogSeeder extends Seeder
{
    public function run(ClimbingCatalogBootstrap $bootstrap): void
    {
        $bootstrap->run();
    }
}
