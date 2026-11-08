<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use App\LegacyImport\Mapping\QualificationMapping;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class QualificationsImporter implements Importer
{
    public function key(): string
    {
        return 'qualifications';
    }

    public function label(): string
    {
        return 'Qualifications';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;
        $legacy = $context->legacy();

        foreach ($legacy->table('qualifications')->orderBy('id')->get() as $qualification) {
            if (! User::query()->whereKey($qualification->user_id)->exists()) {
                $result->skipped++;
                $result->addMessage("Skipped qualification {$qualification->id}: user was not imported.");

                continue;
            }

            $kind = QualificationMapping::kind($qualification->detail_type);

            if ($kind === null) {
                $result->skipped++;
                $result->addMessage("Skipped qualification {$qualification->id}: unknown detail type.");

                continue;
            }

            $table = match ($kind) {
                'scout_permit' => 'scout_permits',
                'mountain_training' => 'mountain_training_qualifications',
                'girlguiding' => 'girlguiding_qualifications',
            };

            $detail = $legacy->table($table)->where('id', $qualification->detail_id)->first();

            if ($detail === null) {
                $result->skipped++;
                $result->addMessage("Skipped qualification {$qualification->id}: missing detail row.");

                continue;
            }

            $mapped = QualificationMapping::fromDetail($qualification->detail_type, $detail, $qualification->expires_on);

            if ($mapped === null) {
                $result->skipped++;

                continue;
            }

            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            DB::table('qualifications')->updateOrInsert(
                ['id' => $qualification->id],
                [
                    'user_id' => $qualification->user_id,
                    'kind' => $mapped['kind'],
                    'payload' => json_encode($mapped['payload']),
                    'expires_on' => $mapped['expires_on'],
                    'created_at' => $qualification->created_at,
                    'updated_at' => $qualification->updated_at,
                ],
            );
        }

        return $result;
    }
}
