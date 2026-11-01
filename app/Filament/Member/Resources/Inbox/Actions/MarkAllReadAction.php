<?php

namespace App\Filament\Member\Resources\Inbox\Actions;

use App\Filament\Concerns\HasStableLivewireKey;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Models\InboxNotification;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;

class MarkAllReadAction extends Action
{
    use HasStableLivewireKey;

    public static function getDefaultName(): ?string
    {
        return 'markAllRead';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Mark all read'))
            ->icon(Heroicon::OutlinedCheck)
            ->visible(function (HasTable $livewire): bool {
                $filter = $livewire->getTableFilterState('read_state') ?? [];

                if (($filter['value'] ?? null) === 'read') {
                    return false;
                }

                return InboxResource::unreadCount() > 0;
            })
            ->extraAttributes([
                'x-cloak' => true,
                'x-show' => '! getSelectedRecordsCount()',
            ])
            ->action(function (): void {
                InboxResource::getEloquentQuery()
                    ->whereNull('read_at')
                    ->each(fn (InboxNotification $record) => $record->markReadForDelivery());
            });
    }
}
