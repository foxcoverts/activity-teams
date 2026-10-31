<?php

namespace App\Filament\Admin\Resources\InboundMails;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\InboundMails\Pages\ListInboundMails;
use App\Filament\Admin\Resources\InboundMails\Pages\ViewInboundMail;
use App\Filament\Admin\Resources\InboundMails\Schemas\InboundMailInfolist;
use App\Filament\Admin\Resources\InboundMails\Tables\InboundMailsTable;
use App\Models\InboundMail;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InboundMailResource extends Resource
{
    protected static ?string $model = InboundMail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static bool $isScopedToTenant = false;

    public static function getNavigationLabel(): string
    {
        return __('Inbound mail');
    }

    public static function getModelLabel(): string
    {
        return __('Inbound mail');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Inbound mail');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return InboundMailInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InboundMailsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInboundMails::route('/'),
            'view' => ViewInboundMail::route('/{record}'),
        ];
    }
}
