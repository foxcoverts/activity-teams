<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Member\Resources\Inbox\InboxResource;

class UnreadInboxWidget extends DashboardSection
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.member.widgets.unread-inbox';

    public function markRead(string $id): void
    {
        $this->dashboard()->markRead([$id]);
        $this->dispatch('dashboard-updated');
        $this->dispatch('refresh-sidebar');
        $this->dispatch('refresh-topbar');
    }

    /**
     * @param  array<int, string>  $ids
     */
    public function markShownRead(array $ids): void
    {
        $this->dashboard()->markRead($ids);
        $this->dispatch('dashboard-updated');
        $this->dispatch('refresh-sidebar');
        $this->dispatch('refresh-topbar');
    }

    public function markAllRead(): void
    {
        $this->dashboard()->markAllRead();
        $this->dispatch('dashboard-updated');
        $this->dispatch('refresh-sidebar');
        $this->dispatch('refresh-topbar');
    }

    protected function sectionData(): array
    {
        return [
            'rows' => $this->dashboard()->unreadPreview(),
            'total' => InboxResource::unreadCount(),
        ];
    }
}
