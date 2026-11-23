<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Schemas;

use App\Filament\Support\Forms\PersonSelect;
use App\Filament\Support\Person;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class IssuedMerchandiseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Issued item'))
                    ->icon(Heroicon::OutlinedGift)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        PersonSelect::make('user_id')
                            ->label(__('Member'))
                            ->options(function (): array {
                                $team = Filament::getTenant();

                                if (! $team instanceof Team) {
                                    return [];
                                }

                                return Person::optionsFor(
                                    $team->members()->orderBy('name')->get(),
                                );
                            })
                            ->required(),
                        Select::make('merchandise_item_id')
                            ->label(__('Item'))
                            ->options(function (): array {
                                $team = Filament::getTenant();

                                if (! $team instanceof Team) {
                                    return [];
                                }

                                return MerchandiseItem::query()
                                    ->forTeam($team)
                                    ->orderBy('title')
                                    ->pluck('title', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $set('merchandise_item_variant_id', static::defaultVariantId(
                                    is_string($state) || is_int($state) ? (string) $state : null,
                                ));
                            }),
                        Select::make('merchandise_item_variant_id')
                            ->label(__('Variant'))
                            ->helperText(__('e.g. size'))
                            ->options(fn (Get $get): array => static::variantOptions(
                                is_string($get('merchandise_item_id')) || is_int($get('merchandise_item_id'))
                                    ? (string) $get('merchandise_item_id')
                                    : null,
                            ))
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('merchandise_item_id')))
                            ->rule(function (Get $get): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $team = Filament::getTenant();
                                    $itemId = $get('merchandise_item_id');

                                    if (! $team instanceof Team || blank($value) || blank($itemId)) {
                                        return;
                                    }

                                    $belongs = MerchandiseItemVariant::query()
                                        ->whereKey($value)
                                        ->where('merchandise_item_id', $itemId)
                                        ->whereHas(
                                            'item',
                                            fn (Builder $items): Builder => $items->whereBelongsTo($team),
                                        )
                                        ->exists();

                                    if (! $belongs) {
                                        $fail(__('The selected variant does not belong to this team’s catalogue.'));
                                    }
                                };
                            }),
                        DatePicker::make('issued_on')
                            ->label(__('Issued on'))
                            ->required()
                            ->default(now())
                            ->native(false),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function variantOptions(?string $itemId): array
    {
        if (blank($itemId)) {
            return [];
        }

        return MerchandiseItemVariant::query()
            ->where('merchandise_item_id', $itemId)
            ->orderBy('sort')
            ->orderBy('label')
            ->pluck('label', 'id')
            ->all();
    }

    public static function defaultVariantId(?string $itemId): ?string
    {
        $options = static::variantOptions($itemId);

        return count($options) === 1 ? (string) array_key_first($options) : null;
    }
}
