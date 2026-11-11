<?php

namespace App\LegacyImport\Importers;

use App\Enums\TeamFeature;
use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TodosImporter implements Importer
{
    public function key(): string
    {
        return 'todos';
    }

    public function label(): string
    {
        return 'Todos';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;
        $team = $context->dryRun ? null : $context->team();

        foreach ($context->legacy()->table('todos')->orderBy('id')->get() as $todo) {
            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            DB::table('tasks')->updateOrInsert(
                ['id' => $todo->id],
                [
                    'team_id' => $team->getKey(),
                    'venue_id' => null,
                    'summary' => $todo->summary,
                    'description' => $todo->description ?? null,
                    'priority' => (int) ($todo->priority ?? 5),
                    'status' => $todo->status ?? 'needs-action',
                    'due_at' => $todo->due_at ?? null,
                    'started_at' => $todo->started_at ?? null,
                    'completed_at' => $todo->completed_at ?? null,
                    'sequence' => (int) ($todo->sequence ?? 0),
                    'created_at' => $todo->created_at,
                    'updated_at' => $todo->updated_at,
                ],
            );

            $location = trim((string) ($todo->location ?? ''));

            if ($location !== '') {
                $existing = DB::table('venues')->where('task_id', $todo->id)->first();
                $venueId = $existing->id ?? (string) Str::ulid();

                DB::table('venues')->updateOrInsert(
                    ['id' => $venueId],
                    [
                        'name' => $location,
                        'booking_id' => null,
                        'task_id' => $todo->id,
                        'created_at' => $existing->created_at ?? $todo->created_at,
                        'updated_at' => $todo->updated_at,
                    ],
                );
                DB::table('tasks')->where('id', $todo->id)->update(['venue_id' => $venueId]);
            }
        }

        if (! $context->dryRun && $team !== null) {
            $features = $team->features ?? [];
            $features[TeamFeature::Tasks->value] = true;
            $team->forceFill(['features' => $features])->save();
        }

        return $result;
    }
}
