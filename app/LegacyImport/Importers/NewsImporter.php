<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use App\LegacyImport\Mapping\NewsBodyMapping;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class NewsImporter implements Importer
{
    public function key(): string
    {
        return 'news';
    }

    public function label(): string
    {
        return 'News posts';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;
        $team = $context->dryRun ? null : $context->team();

        foreach ($context->legacy()->table('news_posts')->orderBy('id')->get() as $post) {
            if (! User::query()->whereKey($post->author_id)->exists()) {
                $result->skipped++;
                $result->addMessage("Skipped news post {$post->id}: author was not imported.");

                continue;
            }

            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            DB::table('news_posts')->updateOrInsert(
                ['id' => $post->id],
                [
                    'team_id' => $team->getKey(),
                    'author_id' => $post->author_id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'body' => NewsBodyMapping::rewrite((string) $post->body),
                    'published_at' => $post->created_at,
                    'notified_at' => $post->created_at,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'deleted_at' => null,
                ],
            );
        }

        return $result;
    }
}
