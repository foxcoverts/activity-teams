<?php

namespace App\Filament\RelationManagers;

use App\Filament\Qualifications\QualificationSchema;
use App\Models\Qualification;
use App\Models\User;
use App\Qualifications\QualificationCatalogue;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class QualificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'qualifications';

    protected static ?string $title = 'Qualifications';

    protected static ?string $modelLabel = 'qualification';

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();
        $actor = auth()->user();

        return ! ($actor instanceof User
            && $owner instanceof User
            && Qualification::canMaintain($actor, $owner));
    }

    public function table(Table $table): Table
    {
        return $table
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
                SelectFilter::make('kind')
                    ->label(__('Type'))
                    ->options(fn (): array => app(QualificationCatalogue::class)->familyOptions(short: true)),
            ])
            ->columns([
                TextColumn::make('type_label')
                    ->label(__('Type')),
                TextColumn::make('summary')
                    ->label(__('Qualification')),
                TextColumn::make('expires_on')
                    ->label(__('Expires'))
                    ->date()
                    ->placeholder('—')
                    ->color(fn (Qualification $record): ?array => $record->expiryColor()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(fn (): string => __('Add :label', [
                        'label' => static::getModelLabel(),
                    ]))
                    ->modalHeading(fn (): string => __('Add :label', [
                        'label' => str(static::getModelLabel())->ucwords()->toString(),
                    ]))
                    ->schema(fn (): array => QualificationSchema::memberFields($this->owner()))
                    ->using(function (array $data): Qualification {
                        $owner = $this->owner();
                        $actor = auth()->user();

                        if (! $owner instanceof User || ! $actor instanceof User || ! Qualification::canMaintain($actor, $owner)) {
                            throw new RuntimeException('This member\'s qualifications cannot be changed.');
                        }

                        $attributes = QualificationSchema::packRecord($data);

                        return Qualification::record($owner, $attributes['kind'], $attributes['payload'], $attributes['expires_on']);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Qualification $record): array => QualificationSchema::memberFields($this->owner(), $record))
                    ->mutateRecordDataUsing(fn (array $data): array => QualificationSchema::unpackRecord($data))
                    ->using(function (array $data, Qualification $record): Qualification {
                        $record->update(QualificationSchema::packRecord($data));

                        return $record;
                    }),
                DeleteAction::make(),
            ]);
    }

    private function owner(): ?User
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof User ? $owner : null;
    }
}
