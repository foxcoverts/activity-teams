<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use App\LegacyImport\Mapping\ActivityMapping;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class KeysImporter implements Importer
{
    public function key(): string
    {
        return 'keys';
    }

    public function label(): string
    {
        return 'Key rings';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;

        foreach ($context->legacy()->table('keys')->orderBy('id')->get() as $legacyKey) {
            $lockName = ActivityMapping::lockForKeyName($legacyKey->name);
            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            $lock = $context->lock($lockName);
            $holderId = filled($legacyKey->holder_id)
                && User::query()->whereKey($legacyKey->holder_id)->exists()
                    ? $legacyKey->holder_id
                    : null;

            DB::table('keys')->updateOrInsert(
                ['id' => $legacyKey->id],
                [
                    'name' => $legacyKey->name,
                    'holder_id' => $holderId,
                    'created_at' => $legacyKey->created_at,
                    'updated_at' => $legacyKey->updated_at,
                ],
            );

            DB::table('key_lock')->updateOrInsert(
                [
                    'key_id' => $legacyKey->id,
                    'lock_id' => $lock->getKey(),
                ],
                [],
            );
        }

        return $result;
    }
}
