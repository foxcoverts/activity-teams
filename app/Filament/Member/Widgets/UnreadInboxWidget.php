<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Support\RefreshesNavigationChrome;

class UnreadInboxWidget extends DashboardSection
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.member.widgets.unread-inbox';

    public function markRead(string $id): void
    {
        $this->dashboard()->markRead([$id]);
        $this->dispatch('dashboard-updated');
        RefreshesNavigationChrome::dispatch($this);
    }

    /**
     * @param  array<int, string>  $ids
     */
    public function markShownRead(array $ids): void
    {
        $this->dashboard()->markRead($ids);
        $this->dispatch('dashboard-updated');
        RefreshesNavigationChrome::dispatch($this);
    }

    public function markAllRead(): void
    {
        $this->dashboard()->markAllRead();
        $this->dispatch('dashboard-updated');
        RefreshesNavigationChrome::dispatch($this);
    }

    protected function sectionData(): array
    {
        return [
            'rows' => $this->dashboard()->unreadPreview(),
            'total' => InboxResource::unreadCount(),
        ];
    }
}
