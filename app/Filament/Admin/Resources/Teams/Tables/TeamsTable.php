<?php

namespace App\Filament\Admin\Resources\Teams\Tables;

use App\Filament\Support\Tables\PersonStackedColumn;
use App\Filament\Teams\Pages\Dashboard as TeamsDashboard;
use App\Models\Team;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Team $record): string => $record->getFilamentAvatarUrl())
                    ->alt(fn (Team $record): string => $record->name)
                    ->imageSize(32)
                    ->square()
                    ->width('1%')
                    ->grow(false),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                PersonStackedColumn::make('managers.name')
                    ->label('Managers')
                    ->placeholder('No managers')
                    ->memberUrl(),
            ])
            ->filters([
                SelectFilter::make('managers.id')
                    ->label('Managers')
                    ->relationship(
                        'activeManagers',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('users.suspended', false)
                            ->whereHas('managedTeams'),
                        hasEmptyOption: true,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('teams')
                    ->label(TeamsDashboard::getNavigationLabel())
                    ->icon(TeamsDashboard::getNavigationIcon())
                    ->color(Palette::positive())
                    ->url(fn (Team $record): string => TeamsDashboard::getUrl(panel: 'teams', tenant: $record)),
            ]);
    }
}
