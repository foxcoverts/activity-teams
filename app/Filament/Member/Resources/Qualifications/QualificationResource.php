<?php

namespace App\Filament\Member\Resources\Qualifications;

use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Resources\Qualifications\Pages\ListQualifications;
use App\Filament\Member\Resources\Qualifications\Pages\ViewQualification;
use App\Models\Qualification;
use App\Models\User;
use App\Qualifications\PermitType;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QualificationResource extends Resource
{
    protected static ?string $model = Qualification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Personal;

    protected static ?int $navigationSort = 2;

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
            ->whereBelongsTo(auth()->user());
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
                    ->color(fn (Qualification $record): ?array => $record->expiryColor())
                    ->visible(fn (Qualification $record): bool => $record->expires_on !== null),
                Group::make()
                    ->schema(fn (Qualification $record): array => collect($record->detailFields())
                        ->map(function (array $field) use ($record): TextEntry {
                            $entry = TextEntry::make('field.'.str($field['label'])->slug())
                                ->label($field['label'])
                                ->state($field['value']);

                            if ($field['label'] === __('Permit type') && filled($record->payload['permit_type'] ?? null)) {
                                $entry->badge()
                                    ->color(PermitType::color((string) $record->payload['permit_type']))
                                    ->icon(PermitType::icon((string) $record->payload['permit_type']));
                            }

                            if ($field['label'] === __('Restrictions')) {
                                $entry->columnSpanFull();
                            }

                            return $entry;
                        })
                        ->all())
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expires_on')
            ->columns([
                TextColumn::make('type_label')
                    ->label(__('Type')),
                TextColumn::make('summary')
                    ->label(__('Qualification')),
                TextColumn::make('expires_on')
                    ->label(__('Expires'))
                    ->date()
                    ->placeholder('—')
                    ->color(fn (Qualification $record): ?array => $record->expiryColor())
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('expired')
                    ->label(__('Expired'))
                    ->placeholder(__('Current'))
                    ->trueLabel(__('Show expired'))
                    ->falseLabel(__('Expired only'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query,
                        false: fn (Builder $query): Builder => $query->whereDate('expires_on', '<', now()->toDateString()),
                        blank: fn (Builder $query): Builder => $query->inDate(),
                    ),
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
