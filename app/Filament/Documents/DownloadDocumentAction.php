<?php

namespace App\Filament\Documents;

use App\Models\Document;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentAction
{
    public static function make(): Action
    {
        return Action::make('download')
            ->label(__('Download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (Document $record): StreamedResponse {
                $user = auth()->user();
                abort_unless($user instanceof User && $user->can('view', $record), 404);

                abort_unless(
                    filled($record->file_path) && Storage::disk('local')->exists($record->file_path),
                    404,
                );

                return Storage::disk('local')->response($record->file_path, $record->file_name);
            });
    }
}
