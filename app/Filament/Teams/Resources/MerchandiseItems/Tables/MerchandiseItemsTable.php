<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Tables;

use App\Enums\MerchandiseAvailability;
use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseSupplier;
use App\Models\Team;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MerchandiseItemsTable
{
    private const NoSupplierOption = '__none';

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label(__('Image'))
                    ->square()
                    ->getStateUsing(fn (MerchandiseItem $record): ?string => $record->thumbnailImage()?->thumbnailUrl()),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->description(fn (MerchandiseItem $record): ?string => $record->description)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->placeholder('-')
                    ->url(fn (MerchandiseItem $record): ?string => $record->supplier
                        ? MerchandiseSupplierResource::getUrl('view', ['record' => $record->supplier])
                        : null)
                    ->color('primary')
                    ->toggleable(),
                TextColumn::make('availability')
                    ->label(__('Availability'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label(__('Variants')),
            ])
            ->filters([
                SelectFilter::make('availability')
                    ->label(__('Availability'))
                    ->options(MerchandiseAvailability::class),
                Filter::make('supplier')
                    ->label(__('Supplier'))
                    ->schema([
                        Select::make('value')
                            ->label(__('Supplier'))
                            ->options(fn (): array => static::supplierFilterOptions())
                            ->disableOptionWhen(fn (mixed $value): bool => static::supplierFilterOptionDisabled($value))
                            ->searchable()
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === self::NoSupplierOption) {
                            return $query->whereNull('merchandise_supplier_id');
                        }

                        return $query->where('merchandise_supplier_id', $value);
                    })
                    ->indicateUsing(function (array $data): array {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return [];
                        }

                        if ($value === self::NoSupplierOption) {
                            return [__('Supplier: :name', ['name' => __('None')])];
                        }

                        $name = MerchandiseSupplier::query()->whereKey($value)->value('name');

                        return filled($name)
                            ? [__('Supplier: :name', ['name' => $name])]
                            : [];
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function supplierFilterOptions(): array
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            return [self::NoSupplierOption => __('None')];
        }

        $suppliers = MerchandiseSupplier::query()
            ->forTeam($team)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [self::NoSupplierOption => __('None')] + $suppliers;
    }

    private static function supplierFilterOptionDisabled(mixed $value): bool
    {
        if (blank($value)) {
            return false;
        }

        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            return true;
        }

        if ($value === self::NoSupplierOption) {
            return MerchandiseItem::query()
                ->forTeam($team)
                ->whereNull('merchandise_supplier_id')
                ->doesntExist();
        }

        return ! MerchandiseItem::query()
            ->forTeam($team)
            ->where('merchandise_supplier_id', $value)
            ->exists();
    }
}
