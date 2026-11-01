<?php

namespace App\Filament\Member\Resources\Inbox\Actions;

use App\Filament\Concerns\HasStableLivewireKey;
use App\Models\InboxNotification;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class MarkSelectedReadBulkAction extends BulkAction
{
    use HasStableLivewireKey;

    public static function getDefaultName(): ?string
    {
        return 'markSelectedRead';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Mark read'))
            ->icon(Heroicon::OutlinedCheck)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $records->each(fn (InboxNotification $record) => $record->markReadForDelivery());
            });
    }
}
