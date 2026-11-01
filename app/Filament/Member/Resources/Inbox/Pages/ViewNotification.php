<?php

namespace App\Filament\Member\Resources\Inbox\Pages;

use App\Filament\Member\Resources\Inbox\InboxResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Marks the notification read and redirects to its URL.
 *
 * Every sent inbox notification has a URL. Do not render the notice here unless a notice exists that cannot have a destination.
 */
class ViewNotification extends ViewRecord
{
    protected static string $resource = InboxResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->markReadForDelivery();
        $this->redirect($this->record->data['url']);
    }
}
