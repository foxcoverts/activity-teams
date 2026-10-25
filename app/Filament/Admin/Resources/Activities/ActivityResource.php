<?php

namespace App\Filament\Admin\Resources\Activities;

use App\Enums\GirlguidingLevel;
use App\Enums\GirlguidingScheme;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Models\Activity;
use App\Models\ActivityQualificationRequirement;
use App\Models\GirlguidingQualification;
use App\Models\MountainTrainingQualification;
use App\Models\ScoutPermit;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('qualificationRequirements');
    }

    /**
     * @return array<int, TextInput|Textarea|Repeater>
     */
    public static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->required(),
            TextInput::make('slug')
                ->helperText(__('Leave blank to generate from the name.'))
                ->alphaDash()
                ->visible(fn (string $operation): bool => $operation === 'edit'),
            Textarea::make('description')
                ->columnSpanFull(),
            Repeater::make('qualificationRequirements')
                ->relationship()
                ->orderColumn('sort')
                ->label(__('Acceptable qualifications'))
                ->helperText(__('Each row is one way to qualify. Leave a field blank for any value. A higher category, permit type, level, or award covers a lower one. Permit restrictions are ignored. Leave this empty when the activity needs no qualification.'))
                ->schema([
                    Select::make('detail_type')
                        ->label(__('Type'))
                        ->options([
                            ScoutPermit::class => __('Scout permit'),
                            MountainTrainingQualification::class => __('Mountain Training'),
                            GirlguidingQualification::class => __('Girlguiding'),
                        ])
                        ->live()
                        ->required(),
                    Select::make('permit_activity')
                        ->label(__('Activity'))
                        ->options(ScoutPermitActivity::class)
                        ->multiple()
                        ->visible(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class)
                        ->dehydrated(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class),
                    Select::make('category')
                        ->options(ScoutPermitCategory::class)
                        ->multiple()
                        ->visible(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class && ActivityQualificationRequirement::allowsClimbingCategory($get('permit_activity')))
                        ->dehydrated(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class && ActivityQualificationRequirement::allowsClimbingCategory($get('permit_activity'))),
                    Select::make('permit_type')
                        ->label(__('Permit type'))
                        ->options(ScoutPermitType::class)
                        ->multiple()
                        ->visible(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class)
                        ->dehydrated(fn (Get $get): bool => $get('detail_type') === ScoutPermit::class),
                    Select::make('award')
                        ->options(MountainTrainingAward::class)
                        ->multiple()
                        ->visible(fn (Get $get): bool => $get('detail_type') === MountainTrainingQualification::class)
                        ->dehydrated(fn (Get $get): bool => $get('detail_type') === MountainTrainingQualification::class),
                    Select::make('scheme')
                        ->options(GirlguidingScheme::class)
                        ->multiple()
                        ->visible(fn (Get $get): bool => $get('detail_type') === GirlguidingQualification::class)
                        ->dehydrated(fn (Get $get): bool => $get('detail_type') === GirlguidingQualification::class),
                    Select::make('level')
                        ->options(GirlguidingLevel::class)
                        ->multiple()
                        ->visible(fn (Get $get): bool => $get('detail_type') === GirlguidingQualification::class)
                        ->dehydrated(fn (Get $get): bool => $get('detail_type') === GirlguidingQualification::class),
                ])
                ->itemLabel(function (array $state): ?string {
                    if (blank($state['detail_type'] ?? null)) {
                        return null;
                    }

                    return (new ActivityQualificationRequirement($state))->summary;
                })
                ->addActionLabel(__('Add alternative'))
                ->defaultItems(0)
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
                TextEntry::make('name'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                RepeatableEntry::make('qualificationRequirements')
                    ->label(__('Acceptable qualifications'))
                    ->placeholder(__('No qualification required.'))
                    ->schema([
                        TextEntry::make('summary')
                            ->hiddenLabel(),
                    ])
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
                TextColumn::make('name')
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
