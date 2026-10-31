<?php

namespace App\Filament\Admin\Resources\Activities\RelationManagers;

use App\Filament\Qualifications\QualificationSchema;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityAudiencesRelationManager extends RelationManager
{
    protected static string $relationship = 'activityAudiences';

    protected static ?string $title = 'Audiences';

    protected static ?string $modelLabel = 'audience';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Audience'))
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('audience_id')
                            ->label(__('Audience'))
                            ->options(fn (?ActivityAudience $record): array => $this->audienceOptions($record))
                            ->default(fn (): ?string => $this->onlyAudienceId())
                            ->required()
                            ->disabledOn('edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        QualificationSchema::requirementRepeater(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (ActivityAudience $record): string => $record->audience?->name ?? __('Audience'))
            ->columns([
                TextColumn::make('audience.name')
                    ->label(__('Audience'))
                    ->searchable(),
                TextColumn::make('requirements')
                    ->label(__('Acceptable qualifications'))
                    ->state(function (ActivityAudience $record): string {
                        $record->loadMissing('qualificationRequirements');

                        if ($record->qualificationRequirements->isEmpty()) {
                            return __('No qualification required.');
                        }

                        return $record->qualificationRequirements
                            ->map(fn ($requirement): string => (string) $requirement->summary)
                            ->implode(', ');
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add audience')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (ActivityAudience $record, DeleteAction $action): void {
                        if (! $record->isUsedByBookings()) {
                            return;
                        }

                        Notification::make()
                            ->title(__('This audience is used by a booking.'))
                            ->danger()
                            ->send();

                        $action->halt();
                    }),
            ]);
    }

    private function onlyAudienceId(): ?string
    {
        $options = $this->audienceOptions();

        if (count($options) !== 1) {
            return null;
        }

        return (string) array_key_first($options);
    }

    /**
     * @return array<string, string>
     */
    private function audienceOptions(?ActivityAudience $current = null): array
    {
        $owner = $this->getOwnerRecord();
        $used = $owner instanceof Activity
            ? $owner->activityAudiences()->pluck('audience_id')
            : collect();

        if ($current !== null) {
            $used = $used->reject(
                fn (mixed $id): bool => (string) $id === (string) $current->audience_id,
            );
        }

        return Audience::query()
            ->when(
                $used->isNotEmpty(),
                fn (Builder $query): Builder => $query->whereNotIn('id', $used),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
