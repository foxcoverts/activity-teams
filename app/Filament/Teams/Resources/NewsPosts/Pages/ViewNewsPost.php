<?php

namespace App\Filament\Teams\Resources\NewsPosts\Pages;

use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Models\InboxNotification;
use App\Models\NewsPost;
use App\Models\User;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewNewsPost extends ViewRecord
{
    protected static string $resource = NewsPostResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = auth()->user();

        if ($user instanceof User) {
            $user->unreadNotifications()
                ->where('data->subject_type', NewsPost::class)
                ->where('data->subject_id', $this->getRecord()->getKey())
                ->get()
                ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
        }
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 404);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.teams.resources.news-posts.article')
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
