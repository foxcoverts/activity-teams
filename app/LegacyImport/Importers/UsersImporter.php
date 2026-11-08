<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use App\LegacyImport\Mapping\UserMapping;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UsersImporter implements Importer
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users and team membership';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;
        $team = $context->dryRun ? null : $context->team();
        $now = now();

        foreach ($context->legacy()->table('users')->orderBy('id')->get() as $legacyUser) {
            if (! UserMapping::shouldImport($legacyUser, $context->legacy())) {
                $result->skipped++;
                $result->addMessage("Skipped suspended user {$legacyUser->id} ({$legacyUser->name}): no responded attendance.");

                if (! $context->dryRun && $team !== null) {
                    DB::table('team_user')
                        ->where('team_id', $team->getKey())
                        ->where('user_id', $legacyUser->id)
                        ->delete();
                }

                continue;
            }

            $mapped = UserMapping::attributes($legacyUser);
            $joinTeam = UserMapping::shouldJoinTeam($legacyUser);
            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            User::withoutEvents(function () use ($legacyUser, $mapped, $now): void {
                DB::table('users')->updateOrInsert(
                    ['id' => $legacyUser->id],
                    [
                        'name' => $legacyUser->name,
                        'email' => $legacyUser->email,
                        'email_verified_at' => $legacyUser->email_verified_at,
                        'password' => $mapped['password'],
                        'timezone' => $legacyUser->timezone ?: 'Europe/London',
                        'under_18' => $mapped['under_18'],
                        'suspended' => $mapped['suspended'],
                        'oversight' => $mapped['oversight'],
                        'is_system_admin' => $mapped['is_system_admin'],
                        'phone' => $legacyUser->phone,
                        'emergency_name' => $legacyUser->emergency_name,
                        'emergency_phone' => $legacyUser->emergency_phone,
                        'feed_token' => $legacyUser->ical_token,
                        'remember_token' => $legacyUser->remember_token,
                        'created_at' => $legacyUser->created_at ?? $now,
                        'updated_at' => $legacyUser->updated_at ?? $now,
                    ],
                );
            });

            if (! $joinTeam) {
                DB::table('team_user')
                    ->where('team_id', $team->getKey())
                    ->where('user_id', $legacyUser->id)
                    ->delete();
                $result->addMessage("Imported user {$legacyUser->id} ({$legacyUser->name}) without Climbing membership (legacy suspended).");

                continue;
            }

            $membershipId = TeamMembership::query()
                ->where('team_id', $team->getKey())
                ->where('user_id', $legacyUser->id)
                ->value('id') ?? (string) Str::ulid();

            DB::table('team_user')->updateOrInsert(
                [
                    'team_id' => $team->getKey(),
                    'user_id' => $legacyUser->id,
                ],
                [
                    'id' => $membershipId,
                    'role' => $mapped['membership_role']->value,
                    'left_at' => null,
                    'created_at' => $legacyUser->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );
        }

        return $result;
    }
}
