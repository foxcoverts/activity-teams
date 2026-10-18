<?php

namespace App\Filament\Team\Resources\Qualifications;

use App\Filament\Team\Resources\Qualifications\Pages\ListQualifications;
use App\Filament\Team\Resources\Qualifications\Pages\ViewQualification;
use App\Models\GirlguidingQualification;
use App\Models\MountainTrainingQualification;
use App\Models\Qualification;
use App\Models\ScoutPermit;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QualificationResource extends Resource
{
    protected static ?string $model = Qualification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Qualifications';

    protected static ?string $recordTitleAttribute = 'summary';

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereBelongsTo(auth()->user())
            ->with('detail');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('type_label')
                    ->label(__('Type')),
                TextEntry::make('expires_on')
                    ->label(__('Expires'))
                    ->date()
                    ->placeholder('—')
                    ->visible(fn (Qualification $record): bool => ! $record->detail instanceof MountainTrainingQualification),
                Group::make([
                    TextEntry::make('detail.activity')
                        ->label(__('Activity'))
                        ->icon(fn (mixed $state): ?Heroicon => $state?->getIcon()),
                    TextEntry::make('detail.category')
                        ->label(__('Category'))
                        ->icon(fn (mixed $state): ?Heroicon => $state?->getIcon())
                        ->visible(fn (Qualification $record): bool => $record->detail instanceof ScoutPermit && $record->detail->category !== null),
                    TextEntry::make('detail.permit_type')
                        ->label(__('Permit type'))
                        ->badge()
                        ->icon(fn (mixed $state): ?Heroicon => $state?->getIcon()),
                    TextEntry::make('detail.restrictions')
                        ->label(__('Restrictions'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Qualification $record): bool => $record->detail instanceof ScoutPermit),
                Group::make([
                    TextEntry::make('detail.award')
                        ->label(__('Award')),
                ])
                    ->columnSpanFull()
                    ->visible(fn (Qualification $record): bool => $record->detail instanceof MountainTrainingQualification),
                Group::make([
                    TextEntry::make('detail.scheme')
                        ->label(__('Scheme')),
                    TextEntry::make('detail.level')
                        ->label(__('Level')),
                ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Qualification $record): bool => $record->detail instanceof GirlguidingQualification),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type_label')
                    ->label(__('Type')),
                TextColumn::make('summary')
                    ->label(__('Qualification')),
                TextColumn::make('expires_on')
                    ->label(__('Expires'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('No qualifications recorded.'))
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualifications::route('/'),
            'view' => ViewQualification::route('/{record}'),
        ];
    }
}
