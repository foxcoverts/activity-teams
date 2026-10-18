<?php

namespace App\Filament\Manage\Resources\Activities;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Activities\Pages\CreateActivity;
use App\Filament\Manage\Resources\Activities\Pages\EditActivity;
use App\Filament\Manage\Resources\Activities\Pages\ListActivities;
use App\Filament\Manage\Resources\Activities\Pages\ViewActivity;
use App\Models\Activity;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationLabel = 'Activity catalog';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    public static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->required(),
            TextInput::make('slug')
                ->helperText(__('Leave blank to generate from the name.')),
            Textarea::make('description')
                ->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::getFormComponents());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'view' => ViewActivity::route('/{record}'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }
}
