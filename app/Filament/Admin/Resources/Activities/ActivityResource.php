<?php

namespace App\Filament\Admin\Resources\Activities;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Filament\Qualifications\QualificationSchema;
use App\Models\Activity;
use App\Models\ActivityQualificationRequirement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

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
            Textarea::make('description')
                ->columnSpanFull(),
            Repeater::make('qualificationRequirements')
                ->relationship()
                ->orderColumn('sort')
                ->label(__('Acceptable qualifications'))
                ->helperText(__('Each row is one way to qualify. Leave a field blank for any value. A higher category, permit type, level, or award covers a lower one. Permit restrictions are ignored. Leave this empty when the activity needs no qualification.'))
                ->schema(QualificationSchema::requirementFields())
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => QualificationSchema::packRequirement($data))
                ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => QualificationSchema::packRequirement($data))
                ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => QualificationSchema::unpackRequirement($data))
                ->itemLabel(function (array $state): ?string {
                    if (blank($state['kind'] ?? null)) {
                        return null;
                    }

                    return (new ActivityQualificationRequirement(QualificationSchema::packRequirement($state)))->summary;
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
