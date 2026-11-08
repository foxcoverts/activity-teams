<?php

namespace App\LegacyImport;

use App\Models\Activity;
use App\Models\Audience;
use App\Models\Lock;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ImportContext
{
    public function __construct(
        public readonly string $legacyConnection = 'legacy',
        public readonly bool $dryRun = false,
        public readonly ?string $documentsSourcePath = null,
        public readonly OutputStyle $output = new OutputStyle(new ArrayInput([]), new NullOutput),
    ) {}

    public function legacy(): Connection
    {
        return DB::connection($this->legacyConnection);
    }

    public function team(): Team
    {
        return Team::query()->where('slug', 'climbing')->firstOrFail();
    }

    public function activity(string $name): Activity
    {
        return Activity::query()->where('name', $name)->firstOrFail();
    }

    public function audience(string $name): Audience
    {
        return Audience::query()->where('name', $name)->firstOrFail();
    }

    public function venue(string $name): Venue
    {
        return Venue::query()->where('name', $name)->whereNull('booking_id')->firstOrFail();
    }

    public function lock(string $name): Lock
    {
        return Lock::query()->where('name', $name)->firstOrFail();
    }

    public function info(string $message): void
    {
        $this->output->writeln($message);
    }
}
