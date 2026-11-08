<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class DocumentsImporter implements Importer
{
    public function key(): string
    {
        return 'documents';
    }

    public function label(): string
    {
        return 'Documents';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;
        $team = $context->dryRun ? null : $context->team();

        foreach ($context->legacy()->table('documents')->orderBy('id')->get() as $document) {
            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            $extension = pathinfo((string) $document->file_name, PATHINFO_EXTENSION) ?: 'pdf';
            $destination = sprintf('documents/%s/%s.%s', $team->getKey(), $document->id, $extension);

            if ($context->documentsSourcePath) {
                $source = rtrim($context->documentsSourcePath, '/').'/'.ltrim((string) $document->file_path, '/');

                if (is_file($source)) {
                    Storage::disk('local')->put($destination, File::get($source));
                } else {
                    $result->addMessage("Document file missing for {$document->id}: {$source}");
                }
            } else {
                $result->addMessage("No --documents-path supplied; metadata imported for {$document->id} without file copy.");
            }

            $notes = trim(implode("\n\n", array_filter([
                filled($document->category) ? 'Category: '.$document->category : null,
                $document->description,
            ])));

            DB::table('documents')->updateOrInsert(
                ['id' => $document->id],
                [
                    'team_id' => $team->getKey(),
                    'uploader_id' => null,
                    'title' => $document->title,
                    'notes' => $notes !== '' ? $notes : null,
                    'file_path' => $destination,
                    'file_name' => $document->file_name,
                    'file_size' => $document->file_size,
                    'file_type' => $document->file_type,
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                    'deleted_at' => $document->deleted_at,
                ],
            );
        }

        return $result;
    }
}
