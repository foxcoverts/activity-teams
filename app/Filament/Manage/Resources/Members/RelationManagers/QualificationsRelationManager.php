<?php

namespace App\Filament\Manage\Resources\Members\RelationManagers;

use App\Enums\GirlguidingLevel;
use App\Enums\GirlguidingScheme;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use App\Models\GirlguidingQualification;
use App\Models\MountainTrainingQualification;
use App\Models\Qualification;
use App\Models\ScoutPermit;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
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
            ->modifyQueryUsing(fn ($query) => $query->with('detail'))
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
                SelectFilter::make('detail_type')
                    ->label(__('Type'))
                    ->options([
                        MountainTrainingQualification::class => __('Mountain Training'),
                        GirlguidingQualification::class => __('Girlguiding'),
                        ScoutPermit::class => __('Scout permit'),
                    ]),
            ])
            ->columns([
                TextColumn::make('type_label')
                    ->label(__('Type')),
                TextColumn::make('summary')
                    ->label(__('Qualification')),
                TextColumn::make('expires_on')
                    ->label(__('Expires'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(fn (): string => __('Add :label', [
                        'label' => static::getModelLabel(),
                    ]))
                    ->modalHeading(fn (): string => __('Add :label', [
                        'label' => str(static::getModelLabel())->ucwords()->toString(),
                    ]))
                    ->schema(fn (): array => [
                        $this->typeSelect(),
                        Group::make(self::scoutFields())
                            ->visible(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class),
                        Group::make(self::mountainFields($this->owner()))
                            ->visible(fn (Get $get): bool => $get('detail_type') === MountainTrainingQualification::class),
                        Group::make(self::girlguidingFields())
                            ->visible(fn (Get $get): bool => $get('detail_type') === GirlguidingQualification::class),
                        self::expiresField(),
                    ])
                    ->using(function (array $data): Qualification {
                        $owner = $this->owner();
                        $actor = auth()->user();

                        if (! $owner instanceof User || ! $actor instanceof User || ! Qualification::canMaintain($actor, $owner)) {
                            throw new RuntimeException('This member\'s qualifications cannot be changed.');
                        }

                        return Qualification::record($owner, self::detailFrom($data), $data['expires_on'] ?? null);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Qualification $record): array => [
                        Placeholder::make('type_label')
                            ->label(__('Type'))
                            ->content($record->type_label),
                        Group::make()
                            ->relationship('detail')
                            ->schema(match ($record->detail_type) {
                                ScoutPermit::class => self::scoutFields(),
                                MountainTrainingQualification::class => self::mountainFields($this->owner(), $record->detail_id),
                                GirlguidingQualification::class => self::girlguidingFields(),
                                default => [],
                            }),
                        self::expiresField()
                            ->visible($record->detail_type !== MountainTrainingQualification::class)
                            ->required($record->detail_type !== MountainTrainingQualification::class),
                    ]),
                DeleteAction::make(),
            ]);
    }

    private function owner(): ?User
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof User ? $owner : null;
    }

    private function typeSelect(): Select
    {
        return Select::make('detail_type')
            ->label(__('Type'))
            ->options([
                ScoutPermit::class => __('Scout permit'),
                MountainTrainingQualification::class => __('Mountain Training'),
                GirlguidingQualification::class => __('Girlguiding'),
            ])
            ->live()
            ->required();
    }

    /**
     * @return array<int, DatePicker|Select|Textarea>
     */
    private static function scoutFields(): array
    {
        return [
            Select::make('activity')
                ->options(ScoutPermitActivity::class)
                ->default(ScoutPermitActivity::ClimbingAndAbseiling)
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    if (! ScoutPermitActivity::isClimbing($state)) {
                        $set('category', null);
                    }
                })
                ->required(),
            Select::make('category')
                ->options(ScoutPermitCategory::class)
                ->default(ScoutPermitCategory::ArtificialTopRope)
                ->visible(fn (Get $get): bool => ScoutPermitActivity::isClimbing($get('activity')))
                ->required(fn (Get $get): bool => ScoutPermitActivity::isClimbing($get('activity')))
                ->dehydrated(fn (Get $get): bool => ScoutPermitActivity::isClimbing($get('activity'))),
            Select::make('permit_type')
                ->label(__('Permit type'))
                ->options(ScoutPermitType::class)
                ->default(ScoutPermitType::Leadership)
                ->required(),
            Textarea::make('restrictions')
                ->placeholder(__('None'))
                ->rows(2)
                ->nullable(),
        ];
    }

    /**
     * @return array<int, Select>
     */
    private static function mountainFields(?User $member, ?string $exceptDetailId = null): array
    {
        return [
            Select::make('award')
                ->options(MountainTrainingAward::class)
                ->required()
                ->disableOptionWhen(function (mixed $value) use ($member, $exceptDetailId): bool {
                    if (! $member instanceof User || blank($value)) {
                        return false;
                    }

                    return MountainTrainingQualification::isHeldBy($member, $value, $exceptDetailId);
                }),
        ];
    }

    /**
     * @return array<int, Select>
     */
    private static function girlguidingFields(): array
    {
        return [
            Select::make('scheme')
                ->options(GirlguidingScheme::class)
                ->default(GirlguidingScheme::Climbing)
                ->required(),
            Select::make('level')
                ->options(GirlguidingLevel::class)
                ->default(GirlguidingLevel::One)
                ->required(),
        ];
    }

    private static function expiresField(): DatePicker
    {
        return DatePicker::make('expires_on')
            ->label(__('Expires'))
            ->visible(fn (Get $get): bool => filled($get('detail_type')) && $get('detail_type') !== MountainTrainingQualification::class)
            ->required(fn (Get $get): bool => filled($get('detail_type')) && $get('detail_type') !== MountainTrainingQualification::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function detailFrom(array $data): Model
    {
        return match ($data['detail_type'] ?? null) {
            ScoutPermit::class => new ScoutPermit([
                'activity' => $data['activity'] ?? null,
                'category' => $data['category'] ?? null,
                'permit_type' => $data['permit_type'] ?? null,
                'restrictions' => $data['restrictions'] ?? null,
            ]),
            MountainTrainingQualification::class => new MountainTrainingQualification([
                'award' => $data['award'] ?? null,
            ]),
            GirlguidingQualification::class => new GirlguidingQualification([
                'scheme' => $data['scheme'] ?? null,
                'level' => $data['level'] ?? null,
            ]),
            default => throw ValidationException::withMessages([
                'detail_type' => __('Choose a qualification type.'),
            ]),
        };
    }
}
