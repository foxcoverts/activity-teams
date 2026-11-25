<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Schemas;

use App\Actions\NormalizeMerchandiseImageAction;
use App\Enums\MerchandiseAvailability;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Item'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(3),
                        Select::make('availability')
                            ->label(__('Availability'))
                            ->options(MerchandiseAvailability::class)
                            ->required()
                            ->default(MerchandiseAvailability::Draft)
                            ->live()
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    static::validateItemVariantAvailability($value, $get('variants') ?? [], $fail);
                                    static::validateAvailableItemHasImage($value, $get('images') ?? [], $fail);
                                },
                            ]),
                        Repeater::make('images')
                            ->label(__('Images'))
                            ->relationship()
                            ->schema([
                                FileUpload::make('path')
                                    ->label(__('Image'))
                                    ->image()
                                    ->disk('public')
                                    ->directory(fn (): string => 'merchandise/'.(Filament::getTenant()?->getKey() ?? 'unassigned'))
                                    ->visibility('public')
                                    ->automaticallyResizeImagesMode('contain')
                                    ->automaticallyResizeImagesToWidth((string) NormalizeMerchandiseImageAction::MaxLongEdge)
                                    ->automaticallyResizeImagesToHeight((string) NormalizeMerchandiseImageAction::MaxLongEdge)
                                    ->automaticallyUpscaleImagesWhenResizing(false)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                    ->maxSize(10240)
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->orderColumn('sort')
                            ->defaultItems(0)
                            ->addActionLabel(__('Add image'))
                            ->reorderable()
                            ->helperText(__('Images are resized for the shop (max :edge px). The first image’s square crop is the card thumbnail. At least one image is required when the item is available.', [
                                'edge' => NormalizeMerchandiseImageAction::MaxLongEdge,
                            ]))
                            ->columnSpanFull(),
                        Repeater::make('variants')
                            ->label(__('Variants'))
                            ->relationship()
                            ->schema([
                                TextInput::make('label')
                                    ->label(__('Label'))
                                    ->helperText(__('e.g. size'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('price')
                                    ->label(__('Price'))
                                    ->prefix('£')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->required()
                                    ->default(0),
                                Select::make('availability')
                                    ->label(__('Availability'))
                                    ->options(MerchandiseAvailability::class)
                                    ->required()
                                    ->default(MerchandiseAvailability::Draft),
                            ])
                            ->orderColumn('sort')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->validationMessages([
                                'min' => __('You must have at least one variant.'),
                            ])
                            ->addActionLabel(__('Add variant'))
                            ->live()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<int, mixed>  $variants
     */
    public static function validateItemVariantAvailability(mixed $itemAvailability, array $variants, \Closure $fail): void
    {
        $availability = static::normalizeAvailability($itemAvailability);

        if ($availability === null) {
            return;
        }

        $variantStates = collect($variants)
            ->map(fn (mixed $row): ?MerchandiseAvailability => static::normalizeAvailability(data_get($row, 'availability')))
            ->filter();

        if ($availability === MerchandiseAvailability::Available) {
            $hasAvailableVariant = $variantStates->contains(MerchandiseAvailability::Available);

            if (! $hasAvailableVariant) {
                $fail(__('An available item needs at least one available variant.'));
            }

            return;
        }

        if ($availability === MerchandiseAvailability::Mothballed) {
            $hasShopOrRetiredVariant = $variantStates->contains(
                fn (MerchandiseAvailability $state): bool => in_array($state, [
                    MerchandiseAvailability::Available,
                    MerchandiseAvailability::Mothballed,
                ], true),
            );

            if (! $hasShopOrRetiredVariant) {
                $fail(__('A mothballed item needs at least one available or mothballed variant.'));
            }
        }
    }

    /**
     * @param  array<int, mixed>  $images
     */
    public static function validateAvailableItemHasImage(mixed $itemAvailability, array $images, \Closure $fail): void
    {
        if (static::normalizeAvailability($itemAvailability) !== MerchandiseAvailability::Available) {
            return;
        }

        $hasImage = collect($images)->contains(fn (mixed $row): bool => filled(data_get($row, 'path')));

        if (! $hasImage) {
            $fail(__('Available items need at least one image.'));
        }
    }

    public static function normalizeAvailability(mixed $value): ?MerchandiseAvailability
    {
        if ($value instanceof MerchandiseAvailability) {
            return $value;
        }

        return MerchandiseAvailability::tryFrom(is_string($value) ? $value : '');
    }
}
