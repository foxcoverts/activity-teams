<?php

namespace App\Filament\Venues;

use App\Models\Venue;
use App\Support\Coords;
use Closure;
use Darkclow4\FilamentMapPicker\Forms\Components\MapPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Intl\Countries;

class VenueLocationSchema
{
    /**
     * @return list<Section>
     */
    public static function components(
        bool $includeNotes = false,
        ?Closure $visible = null,
    ): array {
        $name = TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->live(onBlur: true);

        $details = [$name];

        $details[] = FusedGroup::make([
            TextInput::make('street')
                ->live(onBlur: true),
            TextInput::make('street2'),
        ])
            ->label(__('Street'));

        $details[] = TextInput::make('city')
            ->label(__('City'))
            ->live(onBlur: true);

        $details[] = TextInput::make('region')
            ->label(__('County'));

        $details[] = TextInput::make('postcode')
            ->label(__('Postcode'))
            ->live(onBlur: true);

        $details[] = Select::make('country')
            ->label(__('Country'))
            ->default('GB')
            ->searchable()
            ->live()
            ->options(fn (): array => Countries::getNames());

        if ($includeNotes) {
            $details[] = Textarea::make('notes')
                ->columnSpanFull();
        }

        return [
            Section::make(__('Venue Details'))
                ->icon(Heroicon::BuildingOffice)
                ->columns(1)
                ->columnSpanFull()
                ->schema($details)
                ->visible($visible ?? true),
            Section::make(__('Location'))
                ->icon(Heroicon::Map)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Hidden::make('latitude')
                        ->requiredWith('longitude'),
                    Hidden::make('longitude')
                        ->requiredWith('latitude'),
                    Toggle::make('has_map_pin')
                        ->label(__('Map location'))
                        ->helperText(__('Turn on to place a pin. Turn off to remove the stored coordinates.'))
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Toggle $component, Get $get): void {
                            $component->state(
                                is_numeric($get('latitude')) && is_numeric($get('longitude')),
                            );
                        })
                        ->afterStateUpdated(function (?bool $state, Set $set, Get $get): void {
                            if (! $state) {
                                $set('latitude', null);
                                $set('longitude', null);
                                $set('map', null);

                                return;
                            }

                            if (is_numeric($get('latitude')) && is_numeric($get('longitude'))) {
                                return;
                            }

                            $set('latitude', Coords::DEFAULT_MAP_LATITUDE);
                            $set('longitude', Coords::DEFAULT_MAP_LONGITUDE);
                        }),
                    MapPicker::make('map')
                        ->hiddenLabel()
                        ->mode('click')
                        ->searchable()
                        ->defaultLocation(Coords::DEFAULT_MAP_LATITUDE, Coords::DEFAULT_MAP_LONGITUDE)
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => (bool) $get('has_map_pin'))
                        ->zoom(13)
                        ->height(420)
                        ->live()
                        ->helperText(__('Click the map to move the pin.'))
                        ->afterStateHydrated(function (MapPicker $component, Get $get): void {
                            $latitude = $get('latitude');
                            $longitude = $get('longitude');

                            if (is_numeric($latitude) && is_numeric($longitude)) {
                                $component->state([
                                    'lat' => (float) $latitude,
                                    'lng' => (float) $longitude,
                                ]);
                            }
                        })
                        ->afterStateUpdated(function (?array $state, Set $set): void {
                            $set('latitude', is_numeric($state['lat'] ?? null) ? $state['lat'] : null);
                            $set('longitude', is_numeric($state['lng'] ?? null) ? $state['lng'] : null);
                        }),
                ])
                ->visible($visible ?? true),
        ];
    }

    /**
     * @return list<Step>
     */
    public static function creationSteps(bool $includeNotes = false): array
    {
        return [
            Step::make(__('Venue details'))
                ->schema(static::components(includeNotes: $includeNotes))
                ->afterValidation(function (Get $get): void {
                    static::validateCatalogName([
                        'name' => $get('name'),
                        'postcode' => $get('postcode'),
                    ]);
                }),
            Step::make(__('Similar venues'))
                ->schema([
                    Section::make(__('Check for a similar venue'))
                        ->icon(Heroicon::MagnifyingGlass)
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            Radio::make('similar_venue_id')
                                ->label(__('Similar venues'))
                                ->options(fn (Get $get): array => static::similarOptions($get))
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('use_new_venue', null))
                                ->visible(fn (Get $get): bool => static::similarOptions($get) !== []),
                            Placeholder::make('similar_venues_empty')
                                ->hiddenLabel()
                                ->content(__('No similar venues were found. Continue to review the new venue details.'))
                                ->visible(fn (Get $get): bool => static::similarOptions($get) === []),
                        ]),
                    Section::make(__('New venue'))
                        ->icon(Heroicon::Plus)
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            Radio::make('use_new_venue')
                                ->hiddenLabel()
                                ->options(fn (Get $get): array => [
                                    '1' => __('None of these - create :name as a new venue', [
                                        'name' => (string) ($get('name') ?: __('this venue')),
                                    ]),
                                ])
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('similar_venue_id', null)),
                        ])
                        ->visible(fn (Get $get): bool => static::similarOptions($get) !== []),
                ]),
            Step::make(__('Confirm venue'))
                ->schema([
                    Section::make(__('Venue to use'))
                        ->icon(Heroicon::BuildingOffice)
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('similar_venue_name')
                                ->label(__('Name'))
                                ->state(fn (Get $get): ?string => static::selectedSimilarVenue($get)?->name)
                                ->placeholder('-'),
                            TextEntry::make('similar_venue_address')
                                ->label(__('Address'))
                                ->state(fn (Get $get): ?string => static::selectedSimilarVenueAddress($get))
                                ->placeholder('-'),
                            TextEntry::make('similar_venue_notes')
                                ->label(__('Notes'))
                                ->state(fn (Get $get): ?string => static::selectedSimilarVenue($get)?->notes)
                                ->placeholder('-'),
                            TextEntry::make('similar_venue_map_location')
                                ->label(__('Map location'))
                                ->state(fn (Get $get): ?string => static::selectedSimilarVenueMapLocation($get))
                                ->placeholder('-')
                                ->visible(fn (Get $get): bool => static::selectedSimilarVenue($get)?->coords !== null),
                        ])
                        ->visible(fn (Get $get): bool => filled($get('similar_venue_id'))),
                    Section::make(__('New venue to create'))
                        ->icon(Heroicon::BuildingOffice)
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            Placeholder::make('new_venue_name')
                                ->label(__('Name'))
                                ->content(fn (Get $get): string => (string) ($get('name') ?? '-')),
                            Placeholder::make('new_venue_address')
                                ->label(__('Address'))
                                ->content(fn (Get $get): string => static::newVenueAddress($get)),
                            Placeholder::make('new_venue_notes')
                                ->label(__('Notes'))
                                ->content(fn (Get $get): string => (string) ($get('notes') ?: '-'))
                                ->visible($includeNotes),
                            Placeholder::make('new_venue_pin')
                                ->label(__('Map location'))
                                ->content(fn (Get $get): string => static::newVenuePin($get))
                                ->visible(fn (Get $get): bool => is_numeric($get('latitude'))
                                    && is_numeric($get('longitude'))),
                        ])
                        ->visible(fn (Get $get): bool => ! (static::selectedSimilarVenue($get) instanceof Venue)),
                ]),
        ];
    }

    /**
     * Labels the final button of a modal action wizard after the venue it will use.
     */
    public static function prepareActionWizard(Wizard $wizard): Wizard
    {
        return $wizard->submitAction(new class($wizard) implements Htmlable
        {
            public function __construct(private Wizard $wizard) {}

            public function toHtml(): string
            {
                return Blade::render(<<<'BLADE'
                    <div x-data="{ similarVenueId: $wire.$entangle(@js($statePath)) }">
                        <x-filament::button type="button" size="sm" x-on:click="$wire.callMountedAction()">
                            <span x-show="similarVenueId">{{ __('Use this venue') }}</span>
                            <span x-show="! similarVenueId">{{ __('Create new venue') }}</span>
                        </x-filament::button>
                    </div>
                BLADE, ['statePath' => implode('.', array_filter([$this->wizard->getStatePath(), 'similar_venue_id']))]);
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function venueAttributes(bool $includeNotes = false): array
    {
        return [
            'name',
            'street',
            'street2',
            'city',
            'region',
            'postcode',
            'country',
            'latitude',
            'longitude',
            ...($includeNotes ? ['notes'] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateCatalogName(array $data): void
    {
        if (filled($data['similar_venue_id'] ?? null)) {
            return;
        }

        $existingVenue = Venue::findCatalogNameConflict(
            (string) ($data['name'] ?? ''),
            isset($data['postcode']) ? (string) $data['postcode'] : null,
        );

        if (! ($existingVenue instanceof Venue)) {
            return;
        }

        $messages = Venue::catalogConflictMessages(
            $existingVenue,
            isset($data['postcode']) ? (string) $data['postcode'] : null,
        );

        // Page forms use `data.*`; action wizards often use the bare field name.
        throw ValidationException::withMessages([
            ...$messages,
            ...collect($messages)
                ->mapWithKeys(fn (string $message, string $field): array => [
                    "data.{$field}" => $message,
                ])
                ->all(),
        ]);
    }

    /**
     * Soft-unique failures are raised on name/postcode, which live on step 1.
     * Send the wizard back there so the field errors are visible.
     */
    public static function validateCatalogNameForCreate(CreateRecord $page): void
    {
        try {
            static::validateCatalogName($page->data);
        } catch (ValidationException $exception) {
            static::goToDetailsStep($page->form);
            throw $exception;
        }
    }

    public static function goToDetailsStep(Schema $form): void
    {
        $wizard = $form->getComponent(
            fn (Component $component): bool => $component instanceof Wizard,
            withHidden: true,
        );

        if (! $wizard instanceof Wizard) {
            return;
        }

        $firstStep = $wizard->getChildSchema()->getComponents(withActions: false)[0] ?? null;

        if (! $firstStep instanceof Step) {
            return;
        }

        $wizard->goToStep($firstStep->getKey());
    }

    /**
     * @return array<string, string>
     */
    private static function similarOptions(Get $get): array
    {
        return Venue::similarCatalogOptions([
            'name' => $get('name'),
            'street' => $get('street'),
            'city' => $get('city'),
            'postcode' => $get('postcode'),
            'latitude' => $get('latitude'),
            'longitude' => $get('longitude'),
        ]);
    }

    private static function selectedSimilarVenue(Get $get): ?Venue
    {
        $venueId = $get('similar_venue_id');

        return is_string($venueId) && $venueId !== ''
            ? Venue::query()->find($venueId)
            : null;
    }

    private static function selectedSimilarVenueAddress(Get $get): ?string
    {
        $venue = static::selectedSimilarVenue($get);

        return $venue instanceof Venue
            ? (implode(', ', $venue->location->addressArray()) ?: null)
            : null;
    }

    private static function selectedSimilarVenueMapLocation(Get $get): ?string
    {
        $coords = static::selectedSimilarVenue($get)?->coords;

        return $coords === null
            ? null
            : "{$coords->latitude}, {$coords->longitude}";
    }

    private static function newVenueAddress(Get $get): string
    {
        $country = $get('country');
        $countryName = is_string($country) ? Countries::getName($country) : null;

        return implode(', ', array_filter([
            $get('street'),
            $get('street2'),
            $get('city'),
            $get('region'),
            $get('postcode'),
            $countryName,
        ])) ?: '-';
    }

    private static function newVenuePin(Get $get): string
    {
        return (string) $get('latitude').', '.(string) $get('longitude');
    }
}
