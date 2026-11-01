<?php

namespace App\Filament\Admin\Resources\Teams\Tables;

use App\Filament\Manage\Pages\Dashboard as ManageDashboard;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Models\Team;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('managers.name')
                    ->label('Managers')
                    ->listWithLineBreaks()
                    ->placeholder('No managers')
                    ->url(fn (Team $record, mixed $state): string => MemberResource::getUrl('index', panel: 'manage', tenant: $record)),
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
                Action::make('manage')
                    ->label(ManageDashboard::getNavigationLabel())
                    ->icon(ManageDashboard::getNavigationIcon())
                    ->color(Palette::positive())
                    ->url(fn (Team $record): string => ManageDashboard::getUrl(panel: 'manage', tenant: $record)),
            ]);
    }
}
