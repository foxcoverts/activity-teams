<?php

namespace App\Console\Commands;

use App\LegacyImport\Mapping\ActivityMapping;
use App\LegacyImport\Mapping\UserMapping;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Key;
use App\Models\NewsPost;
use App\Models\Qualification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('legacy:verify')]
#[Description('Compare imported Climbing data against the legacy database inventory')]
class LegacyVerifyCommand extends Command
{
    public function handle(): int
    {
        try {
            $legacy = DB::connection('legacy');
            $legacy->getPdo();
        } catch (Throwable $exception) {
            $this->error('Cannot connect to the legacy database. Set LEGACY_DB_* in the environment.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $team = Team::query()->where('slug', 'climbing')->first();

        if ($team === null) {
            $this->error('Climbing team is missing. Run legacy:import (catalog) first.');

            return self::FAILURE;
        }

        $importableUsers = $legacy->table('users')->get()
            ->filter(fn (object $user): bool => UserMapping::shouldImport($user, $legacy));
        $importableUserIds = $importableUsers->pluck('id')->all();
        $expectedUsers = $importableUsers->count();
        $expectedTeamMembers = $importableUsers
            ->filter(fn (object $user): bool => UserMapping::shouldJoinTeam($user))
            ->count();
        $expectedQuals = $legacy->table('qualifications')->whereIn('user_id', $importableUserIds)->count();
        $expectedKeys = $legacy->table('keys')->count();
        $expectedNews = $legacy->table('news_posts')->whereIn('author_id', $importableUserIds)->count();
        $expectedDocs = $legacy->table('documents')->count();

        $importableBookings = $legacy->table('bookings')->get()->filter(function ($booking): bool {
            $mapping = ActivityMapping::for($booking->activity, $booking->group_name, $booking->location);

            return $mapping !== null && ($mapping['mode'] ?? null) !== ActivityMapping::DROP;
        })->count();

        $rows = [
            ['users', $expectedUsers, User::query()->count()],
            ['team_user', $expectedTeamMembers, DB::table('team_user')->where('team_id', $team->getKey())->count()],
            ['qualifications', $expectedQuals, Qualification::query()->count()],
            ['keys', $expectedKeys, Key::query()->count()],
            ['bookings (importable)', $importableBookings, Booking::query()->where('team_id', $team->getKey())->count()],
            ['news_posts', $expectedNews, NewsPost::query()->where('team_id', $team->getKey())->count()],
            ['documents', $expectedDocs, Document::query()->where('team_id', $team->getKey())->withTrashed()->count()],
        ];

        $ok = true;

        $this->table(['Domain', 'Legacy / expected', 'Imported'], $rows);

        foreach ($rows as [$label, $expected, $actual]) {
            if ((int) $expected !== (int) $actual) {
                $this->warn("Mismatch for {$label}: expected {$expected}, got {$actual}.");
                $ok = false;
            }
        }

        $orphans = DB::table('booking_user')
            ->leftJoin('users', 'users.id', '=', 'booking_user.user_id')
            ->whereNull('users.id')
            ->count();

        if ($orphans > 0) {
            $this->warn("Found {$orphans} booking_user rows with missing users.");
            $ok = false;
        }

        $this->newLine();
        $this->info('Deferred / not imported (remain on legacy DB):');

        foreach (['todos', 'kit_checks', 'mail_logs'] as $table) {
            if (! Schema::connection('legacy')->hasTable($table)) {
                $this->line("  {$table}: (table absent)");

                continue;
            }

            $this->line("  {$table}: ".$legacy->table($table)->count());
        }

        if ($ok) {
            $this->info('Verify passed for core imported domains.');

            return self::SUCCESS;
        }

        $this->error('Verify found mismatches.');

        return self::FAILURE;
    }
}
