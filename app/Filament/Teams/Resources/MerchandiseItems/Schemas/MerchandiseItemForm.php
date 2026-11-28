<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Schemas;

use App\Actions\NormalizeMerchandiseImageAction;
use App\Actions\SyncMerchandiseVariantOptionSelectionsAction;
use App\Enums\MerchandiseAvailability;
use App\Models\MerchandiseItemVariant;
use App\Models\MerchandiseSupplier;
use App\Models\Team;
use App\Support\MerchandiseVariantOptions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class MerchandiseItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make(__('Item'))
                    ->columnSpanFull()
                    ->persistTabInQueryString('item-tab')
                    ->tabs([
                        Tab::make(__('Details'))
                            ->icon(Heroicon::OutlinedShoppingBag)
                            ->schema([
                                Section::make(__('Details'))
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
                                        Select::make('merchandise_supplier_id')
                                            ->label(__('Supplier'))
                                            ->options(function (): array {
                                                $team = Filament::getTenant();

                                                if (! $team instanceof Team) {
                                                    return [];
                                                }

                                                return MerchandiseSupplier::query()
                                                    ->forTeam($team)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->all();
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->placeholder(__('None')),
                                        TextInput::make('supplier_code')
                                            ->label(__('Supplier code'))
                                            ->maxLength(255)
                                            ->nullable()
                                            ->helperText(__('Product or catalogue reference used by the supplier.')),
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
                                                    static::validateOptionAxisCount($get('optionAxes') ?? [], $fail);
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
                                    ]),
                            ]),
                        Tab::make(__('Variants'))
                            ->icon(Heroicon::OutlinedSwatch)
                            ->schema([
                                Section::make(__('Options'))
                                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->description(__('Optional dimensions such as Size and Colour. Leave empty for a simple labelled list of variants.'))
                                    ->schema([
                                        Repeater::make('optionAxes')
                                            ->label(__('Axes'))
                                            ->relationship()
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label(__('Name'))
                                                    ->placeholder(__('e.g. Size'))
                                                    ->required()
                                                    ->maxLength(255),
                                                Repeater::make('values')
                                                    ->label(__('Values'))
                                                    ->relationship()
                                                    ->schema([
                                                        TextInput::make('label')
                                                            ->label(__('Label'))
                                                            ->placeholder(__('e.g. M'))
                                                            ->required()
                                                            ->maxLength(255),
                                                    ])
                                                    ->orderColumn('sort')
                                                    ->minItems(1)
                                                    ->defaultItems(1)
                                                    ->addActionLabel(__('Add value'))
                                                    ->columnSpanFull(),
                                            ])
                                            ->orderColumn('sort')
                                            ->defaultItems(0)
                                            ->maxItems(MerchandiseVariantOptions::MaxAxes)
                                            ->addActionLabel(__('Add axis'))
                                            ->live()
                                            ->helperText(__('Up to :max axes (for example Size and Colour).', [
                                                'max' => MerchandiseVariantOptions::MaxAxes,
                                            ]))
                                            ->columnSpanFull(),
                                    ]),
                                Section::make(__('SKUs'))
                                    ->icon(Heroicon::OutlinedRectangleStack)
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->headerActions([
                                        Action::make('generateCombinations')
                                            ->label(__('Generate missing combinations'))
                                            ->icon(Heroicon::OutlinedSparkles)
                                            ->visible(fn (LivewireComponent $livewire): bool => static::axesHaveValues(static::optionAxesFromLivewire($livewire)))
                                            ->action(function (LivewireComponent $livewire): void {
                                                $axes = static::optionAxesFromLivewire($livewire);
                                                $variants = is_array(data_get($livewire, 'data.variants'))
                                                    ? data_get($livewire, 'data.variants')
                                                    : [];
                                                $before = count($variants);
                                                $next = SyncMerchandiseVariantOptionSelectionsAction::appendMissingCombinationsToVariantState(
                                                    $axes,
                                                    $variants,
                                                );
                                                data_set($livewire, 'data.variants', $next);
                                                $added = count($next) - $before;

                                                Notification::make()
                                                    ->title($added > 0
                                                        ? trans_choice(':count combination added|:count combinations added', $added, ['count' => $added])
                                                        : __('All combinations are already listed'))
                                                    ->success()
                                                    ->send();
                                            }),
                                    ])
                                    ->schema([
                                        Repeater::make('variants')
                                            ->label(__('Variants'))
                                            ->relationship()
                                            ->schema(fn (LivewireComponent $livewire): array => static::variantSchema(
                                                static::optionAxesFromLivewire($livewire),
                                            ))
                                            ->orderColumn('sort')
                                            ->minItems(1)
                                            ->defaultItems(1)
                                            ->validationMessages([
                                                'min' => __('You must have at least one variant.'),
                                            ])
                                            ->rules([
                                                fn (LivewireComponent $livewire): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($livewire): void {
                                                    static::validateUniqueOptionCombinations(
                                                        static::optionAxesFromLivewire($livewire),
                                                        is_array($value) ? $value : [],
                                                        $fail,
                                                    );
                                                },
                                            ])
                                            ->addActionLabel(__('Add variant'))
                                            ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                                                if (! filled($data['id'] ?? null)) {
                                                    return $data;
                                                }

                                                $variant = MerchandiseItemVariant::query()
                                                    ->with('optionValues')
                                                    ->find($data['id']);

                                                if ($variant === null) {
                                                    return $data;
                                                }

                                                $data['option_value_ids'] = $variant->optionValues
                                                    ->mapWithKeys(fn ($value): array => [
                                                        (string) $value->merchandise_item_option_axis_id => (string) $value->getKey(),
                                                    ])
                                                    ->all();

                                                return $data;
                                            })
                                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::stripOptionValueIds($data))
                                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => static::stripOptionValueIds($data))
                                            ->live()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make(__('Stock'))
                            ->icon(Heroicon::OutlinedCube)
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->schema([
                                Section::make(__('On hand'))
                                    ->icon(Heroicon::OutlinedCube)
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->description(__('Cupboard counts per variant. New variants start at 0. Adjust here when stock changes outside picking and goods in.'))
                                    ->schema([
                                        Repeater::make('stock_levels')
                                            ->label(__('Variants'))
                                            ->schema([
                                                Hidden::make('id'),
                                                Placeholder::make('label_display')
                                                    ->label(__('Variant'))
                                                    ->content(fn (Get $get): string => filled($get('label')) ? (string) $get('label') : '—'),
                                                Placeholder::make('availability_display')
                                                    ->label(__('Availability'))
                                                    ->content(function (Get $get): string {
                                                        $availability = MerchandiseAvailability::tryFrom((string) ($get('availability') ?? ''));

                                                        return $availability?->getLabel() ?? '—';
                                                    }),
                                                TextInput::make('stock_quantity')
                                                    ->label(__('On hand'))
                                                    ->numeric()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->default(0),
                                            ])
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make(__('Personalisation'))
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema([
                                Section::make(__('Personalisation'))
                                    ->icon(Heroicon::OutlinedPencilSquare)
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->description(__('Optional free-text fields collected when a member adds this item to their basket.'))
                                    ->schema([
                                        Repeater::make('personalisation_fields')
                                            ->label(__('Fields'))
                                            ->schema([
                                                Hidden::make('key')
                                                    ->default(fn (): string => (string) Str::ulid()),
                                                TextInput::make('label')
                                                    ->label(__('Field label'))
                                                    ->placeholder(__('e.g. Name on shirt'))
                                                    ->required()
                                                    ->maxLength(255),
                                                Toggle::make('required')
                                                    ->label(__('Required'))
                                                    ->default(true),
                                            ])
                                            ->defaultItems(0)
                                            ->addActionLabel(__('Add personalisation field'))
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $optionAxes
     * @return list<Component>
     */
    private static function variantSchema(array $optionAxes): array
    {
        $axes = collect($optionAxes)
            ->filter(fn (mixed $axis): bool => is_array($axis) && filled($axis['name'] ?? null));

        $fields = [];

        if ($axes->isEmpty()) {
            $fields[] = TextInput::make('label')
                ->label(__('Label'))
                ->placeholder(__('e.g. size'))
                ->required()
                ->maxLength(255);
        } else {
            $fields[] = Placeholder::make('previousLabel')
                ->label(__('Previous label'))
                ->content(fn (Get $get): string => filled($get('label')) ? (string) $get('label') : '—')
                ->helperText(__('Helps match this SKU when adding options. On save the label is built from the selected values.'))
                ->visible(fn (Get $get): bool => filled($get('label')));

            foreach ($axes as $axisKey => $axis) {
                $options = collect($axis['values'] ?? [])
                    ->filter(fn (mixed $value): bool => is_array($value) && filled($value['label'] ?? null))
                    ->mapWithKeys(function (array $value, string|int $valueKey): array {
                        $id = $value['id'] ?? $valueKey;

                        return [(string) $id => (string) $value['label']];
                    })
                    ->all();

                $fields[] = Select::make('option_value_ids.'.(string) ($axis['id'] ?? $axisKey))
                    ->label((string) $axis['name'])
                    ->options($options)
                    ->required();
            }

            $fields[] = Hidden::make('label')
                ->dehydrated();
        }

        $fields[] = TextInput::make('price')
            ->label(__('Price'))
            ->prefix('£')
            ->numeric()
            ->inputMode('decimal')
            ->minValue(0)
            ->step(0.01)
            ->required()
            ->default('0.00')
            ->formatStateUsing(fn (mixed $state): ?string => static::formatPriceState($state))
            ->dehydrateStateUsing(fn (mixed $state): ?string => static::formatPriceState($state))
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, mixed $state): void {
                $formatted = static::formatPriceState($state);

                if ($formatted !== null && (string) $state !== $formatted) {
                    $component->state($formatted);
                }
            });

        $fields[] = Select::make('availability')
            ->label(__('Availability'))
            ->options(MerchandiseAvailability::class)
            ->required()
            ->default(MerchandiseAvailability::Draft);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function stripOptionValueIds(array $data): array
    {
        unset($data['option_value_ids']);

        if (! filled($data['label'] ?? null)) {
            $data['label'] = __('Variant');
        }

        if (array_key_exists('price', $data)) {
            $data['price'] = static::formatPriceState($data['price']) ?? '0.00';
        }

        return $data;
    }

    private static function formatPriceState(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (! is_numeric($state)) {
            return null;
        }

        return number_format((float) $state, 2, '.', '');
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

    /**
     * @param  array<int, mixed>  $axes
     */
    public static function validateOptionAxisCount(array $axes, \Closure $fail): void
    {
        $count = collect($axes)
            ->filter(fn (mixed $axis): bool => is_array($axis) && filled($axis['name'] ?? null))
            ->count();

        if ($count > MerchandiseVariantOptions::MaxAxes) {
            $fail(__('An item can have at most :max option axes.', [
                'max' => MerchandiseVariantOptions::MaxAxes,
            ]));
        }
    }

    /**
     * @param  array<int, mixed>  $axes
     * @param  array<int, mixed>  $variants
     */
    public static function validateUniqueOptionCombinations(array $axes, array $variants, \Closure $fail): void
    {
        $axisCount = collect($axes)
            ->filter(fn (mixed $axis): bool => is_array($axis) && filled($axis['name'] ?? null))
            ->count();

        if ($axisCount === 0) {
            return;
        }

        $seen = [];

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $valueIds = collect($variant['option_value_ids'] ?? [])
                ->filter(fn (mixed $id): bool => filled($id))
                ->map(fn (mixed $id): string => (string) $id)
                ->values();

            if ($valueIds->count() !== $axisCount) {
                continue;
            }

            $key = MerchandiseVariantOptions::combinationKey($valueIds->all());

            if (isset($seen[$key])) {
                $fail(__('Each variant must use a unique combination of options.'));

                return;
            }

            $seen[$key] = true;
        }
    }

    /**
     * @param  array<int, mixed>  $axes
     */
    private static function axesHaveValues(array $axes): bool
    {
        return collect($axes)
            ->filter(fn (mixed $axis): bool => is_array($axis) && filled($axis['name'] ?? null))
            ->contains(function (array $axis): bool {
                return collect($axis['values'] ?? [])
                    ->contains(fn (mixed $value): bool => is_array($value) && filled($value['label'] ?? null));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private static function optionAxesFromLivewire(LivewireComponent $livewire): array
    {
        $axes = data_get($livewire, 'data.optionAxes');

        return is_array($axes) ? $axes : [];
    }

    public static function normalizeAvailability(mixed $value): ?MerchandiseAvailability
    {
        if ($value instanceof MerchandiseAvailability) {
            return $value;
        }

        return MerchandiseAvailability::tryFrom(is_string($value) ? $value : '');
    }
}
