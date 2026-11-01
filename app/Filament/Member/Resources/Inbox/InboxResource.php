<?php

namespace App\Filament\Member\Resources\Inbox;

use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Resources\Inbox\Pages\ListNotifications;
use App\Filament\Member\Resources\Inbox\Pages\ViewNotification;
use App\Filament\Member\Resources\Inbox\Tables\NotificationsTable;
use App\Models\InboxNotification;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InboxResource extends Resource
{
    protected static ?string $model = InboxNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $modelLabel = 'Notification';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Personal;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?string $breadcrumb = 'Inbox';

    protected static ?string $slug = 'inbox';

    public static function table(Table $table): Table
    {
        return NotificationsTable::configure($table);
    }

    /**
     * @return Builder<InboxNotification>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->where('notifiable_type', $user instanceof User ? $user->getMorphClass() : User::class)
            ->where('notifiable_id', $user instanceof User ? $user->getKey() : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
            'view' => ViewNotification::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::unreadCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function unreadCount(): int
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }
}
