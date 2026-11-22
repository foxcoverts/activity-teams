<?php

namespace App\Filament\Admin\Resources\Activities\RelationManagers;

use App\Filament\Admin\Resources\Activities\Schemas\ActivityAudienceForm;
use App\Filament\Support\RefreshesNavigationChrome;
use App\Models\Activity;
use App\Models\ActivityAudience;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Component;

class ActivityAudiencesRelationManager extends RelationManager
{
    protected static string $relationship = 'activityAudiences';

    protected static ?string $title = 'Audiences';

    protected static ?string $modelLabel = 'audience';

    public function form(Schema $schema): Schema
    {
        $components = ActivityAudienceForm::rowSchema(
            fn (?ActivityAudience $record): array => $this->audienceOptions($record),
            inRepeater: false,
        );

        $audienceSelect = $components[0] ?? null;
        assert($audienceSelect instanceof Select);

        $audienceSelect
            ->default(fn (): ?string => $this->onlyAudienceId())
            ->disabledOn('edit')
            ->dehydrated(fn (string $operation): bool => $operation === 'create');

        return $schema
            ->components($components);
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
                            return __('This audience needs no qualifications.');
                        }

                        return $record->qualificationRequirements
                            ->map(fn ($requirement): string => (string) $requirement->summary)
                            ->implode(', ');
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add audience'))
                    ->modalHeading(__('Add audience'))
                    ->modalIcon(Heroicon::OutlinedMegaphone)
                    ->after(function (Component $livewire): void {
                        RefreshesNavigationChrome::dispatch($livewire);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalIcon(Heroicon::OutlinedMegaphone),
                DeleteAction::make()
                    ->before(function (ActivityAudience $record, DeleteAction $action): void {
                        if ($record->isUsedByBookings()) {
                            Notification::make()
                                ->title(__('This audience is used by a booking.'))
                                ->danger()
                                ->send();

                            $action->halt();
                        }

                        $siblings = $record->activity?->activityAudiences()->count() ?? 0;

                        if ($siblings <= 1) {
                            Notification::make()
                                ->title(__('An activity must keep at least one audience.'))
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->after(function (Component $livewire): void {
                        RefreshesNavigationChrome::dispatch($livewire);
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

        return ActivityAudienceForm::optionsExcluding($used, $current?->audience_id);
    }
}
