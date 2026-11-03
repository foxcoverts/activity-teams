<?php

namespace App\Filament\Member\Pages;

use App\Enums\Incident\Gender;
use App\Enums\Incident\Injury;
use App\Enums\Incident\MembershipType;
use App\Filament\Schemas\ContactFields;
use App\Mail\IncidentReport as IncidentReportMail;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\Incident\IncidentReportData;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Propaganistas\LaravelPhone\Rules\Phone;

class ReportIncident extends Page
{
    private const VENUE_COLUMNS = [
        'venues.id',
        'venues.name',
        'venues.street',
        'venues.street2',
        'venues.city',
        'venues.region',
        'venues.postcode',
        'venues.country',
    ];

    protected static ?string $slug = 'report-incident';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Locked]
    public bool $locationWasCustomized = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isActive()
            && ! $user->isSuspended()
            && $user->teams()->exists();
    }

    public static function getNavigationLabel(): string
    {
        return __('Report an incident');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Report an incident');
    }

    public function mount(): void
    {
        $user = $this->actor();
        $teams = $user->teams()->orderBy('name')->get();
        $team = $teams->count() === 1 ? $teams->sole() : null;
        $venue = $team instanceof Team ? $this->singleVenueForTeam($team) : null;
        $now = now();

        $this->form->fill([
            'team_id' => $team?->getKey(),
            'date' => $now->toDateString(),
            'time' => $now->format('H:i'),
            'venue_id' => $venue?->getKey(),
            'location_name' => $venue === null ? null : $this->venuePlaceOfIncident($venue),
            'location_description' => null,
            'reporter_name' => $user->name,
            'reporter_email' => $user->email,
            'reporter_phone' => $user->getAttributes()['phone'] ?? null,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('Context'))
                    ->schema([$this->contextSection()]),
                Step::make(__('Who'))
                    ->schema([$this->peopleSection()]),
                Step::make(__('Response'))
                    ->schema([$this->responseSection()]),
                Step::make(__('Account'))
                    ->schema([$this->accountSection()]),
            ])
                ->submitAction(new HtmlString(Blade::render(
                    '<x-filament::button type="submit">{{ __(\'Submit report\') }}</x-filament::button>',
                ))),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('submit'),
        ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $actor = $this->actor();
        $team = $actor->teams()->whereKey($data['team_id'])->first();

        if (! $team instanceof Team) {
            throw ValidationException::withMessages([
                'data.team_id' => __('Select a team you currently belong to.'),
            ]);
        }

        $managers = $team->activeManagers()
            ->get()
            ->filter(fn (User $manager): bool => filled($manager->email))
            ->values();

        if ($managers->isEmpty()) {
            throw ValidationException::withMessages([
                'data.team_id' => __('This team has no active managers who can receive the report.'),
            ]);
        }

        $report = IncidentReportData::from($data, $team->name);
        $recipients = $managers
            ->map(fn (User $manager): Address => new Address($manager->email, $manager->name))
            ->all();

        Mail::to($recipients)
            ->cc($report->reporterMailAddress())
            ->queue(new IncidentReportMail($report));

        Notification::make()
            ->success()
            ->title(__('Incident report queued for delivery'))
            ->send();

        $this->redirect(static::getUrl(panel: 'member'));
    }

    private function contextSection(): Section
    {
        return Section::make(__('Incident context'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->columns(1)
            ->columnSpanFull()
            ->schema([
                Select::make('team_id')
                    ->label(__('Responsible team'))
                    ->autofocus(fn (): bool => blank($this->data['team_id'] ?? null))
                    ->options(fn (): array => $this->actor()->teams()->orderBy('name')->pluck('name', 'teams.id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if ($this->locationWasCustomized) {
                            return;
                        }

                        $set('venue_id', null);
                        $set('location_name', null);
                        $set('location_description', null);
                        $team = $this->teamFor($state);
                        $venue = $team === null ? null : $this->singleVenueForTeam($team);

                        if ($venue instanceof Venue) {
                            $set('venue_id', (string) $venue->getKey());
                            $this->copyVenueLocation($set, $venue);
                        }
                    }),
                DatePicker::make('date')
                    ->label(__('Date of incident'))
                    ->autofocus(fn (): bool => filled($this->data['team_id'] ?? null))
                    ->required(),
                TimePicker::make('time')
                    ->label(__('Time of incident'))
                    ->seconds(false)
                    ->required(),
                Hidden::make('venue_id')->dehydrated(false),
                TextInput::make('location_name')
                    ->label(__('Place of incident'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $this->markLocationCustomized())
                    ->suffixAction(
                        Action::make('searchVenue')
                            ->label(__('Search venues'))
                            ->icon(Heroicon::MagnifyingGlass)
                            ->iconButton()
                            ->tooltip(__('Search team venues'))
                            ->modalHeading(__('Select a venue'))
                            ->modalSubmitActionLabel(__('Use venue'))
                            ->disabled(fn (): bool => $this->venueOptions($this->data['team_id'] ?? null) === [])
                            ->schema([
                                Select::make('venue_id')
                                    ->label(__('Venue'))
                                    ->options(fn (): array => $this->venueOptions($this->data['team_id'] ?? null))
                                    ->searchable()
                                    ->required(),
                            ])
                            ->action(function (array $data, Set $schemaSet): void {
                                $team = $this->teamFor($this->data['team_id'] ?? null);
                                $venue = filled($data['venue_id'] ?? null)
                                    ? $team?->venues()->whereKey($data['venue_id'])->first(self::VENUE_COLUMNS)
                                    : null;

                                if (! $venue instanceof Venue) {
                                    throw ValidationException::withMessages([
                                        'venue_id' => __('Choose a venue available to the selected team.'),
                                    ]);
                                }

                                $this->locationWasCustomized = true;
                                $schemaSet('venue_id', (string) $venue->getKey());
                                $this->copyVenueLocation($schemaSet, $venue);
                            }),
                    ),
                Textarea::make('location_description')
                    ->label(__('Location details'))
                    ->placeholder(__('For example: back steps, warden\'s hut, by the picnic bench.'))
                    ->helperText(__('Say where on site the incident happened.'))
                    ->required()
                    ->rows(3)
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $this->markLocationCustomized()),
            ]);
    }

    private function peopleSection(): Section
    {
        return Section::make(__('People involved'))
            ->icon(Heroicon::OutlinedUserGroup)
            ->columns(1)
            ->columnSpanFull()
            ->schema([
                $this->yesNoRadio('injured', __('Was anyone injured?'))
                    ->live()
                    ->required(),
                TextInput::make('injured_name')
                    ->label(__('Name of injured person'))
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes')
                    ->maxLength(255),
                DatePicker::make('injured_dob')
                    ->label(__('Date of birth'))
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes'),
                Select::make('injured_gender')
                    ->label(__('Gender'))
                    ->options(Gender::class)
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes'),
                Select::make('membership_type')
                    ->label(__('Membership type'))
                    ->options(MembershipType::groups())
                    ->searchable()
                    ->live()
                    ->required(),
                TextInput::make('group_name')
                    ->label(__('Group name'))
                    ->required(fn (Get $get): bool => ! self::isPublicMembership($get))
                    ->visible(fn (Get $get): bool => ! self::isPublicMembership($get))
                    ->maxLength(255),
                TextInput::make('contact_name')
                    ->label(__('Contact name'))
                    ->required()
                    ->maxLength(255),
                ContactFields::phoneInput('contact_phone')
                    ->label(__('Contact phone'))
                    ->required(fn (Get $get): bool => self::isPublicMembership($get))
                    ->rule(new Phone()->country('GB'))
                    ->visible(fn (Get $get): bool => self::isPublicMembership($get)),
                Textarea::make('contact_address')
                    ->label(__('Contact address'))
                    ->required(fn (Get $get): bool => self::isPublicMembership($get))
                    ->visible(fn (Get $get): bool => self::isPublicMembership($get))
                    ->rows(3),
            ]);
    }

    private function responseSection(): Section
    {
        return Section::make(__('Response and damage'))
            ->icon(Heroicon::OutlinedHeart)
            ->columns(1)
            ->columnSpanFull()
            ->schema([
                CheckboxList::make('injuries')
                    ->label(__('Injuries'))
                    ->options(Injury::class)
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes'),
                $this->yesNoRadio('emergency_services', __('Were emergency services called?'))
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes'),
                $this->yesNoRadio('hospital', __('Did the injured person go directly to hospital for treatment?'))
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes'),
                $this->yesNoRadio('damaged', __('Was property damaged?'))
                    ->required(),
            ]);
    }

    private function accountSection(): Section
    {
        return Section::make(__('Incident and reporter details'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->columns(1)
            ->columnSpanFull()
            ->schema([
                Textarea::make('details')
                    ->label(__('What happened?'))
                    ->required()
                    ->rows(6),
                Textarea::make('first_aid')
                    ->label(__('First aid given'))
                    ->required(fn (Get $get): bool => $get('injured') === 'yes')
                    ->visible(fn (Get $get): bool => $get('injured') === 'yes')
                    ->rows(4),
                TextInput::make('reporter_name')
                    ->label(__('Your name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('reporter_email')
                    ->label(__('Your email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                ContactFields::phoneInput('reporter_phone')
                    ->label(__('Your phone'))
                    ->required()
                    ->rule(new Phone()->country('GB')),
            ]);
    }

    private function yesNoRadio(string $name, string $label): Radio
    {
        return Radio::make($name)
            ->label($label)
            ->options([
                'yes' => __('Yes'),
                'no' => __('No'),
            ])
            ->inline();
    }

    private static function isPublicMembership(Get $get): bool
    {
        return MembershipType::tryFrom((string) $get('membership_type'))?->isPublic() ?? false;
    }

    /**
     * @return array<string, string>
     */
    private function venueOptions(mixed $teamId): array
    {
        $team = $this->teamFor($teamId);

        if (! $team instanceof Team) {
            return [];
        }

        $options = $team->venues()
            ->orderBy('name')
            ->get(self::VENUE_COLUMNS)
            ->mapWithKeys(fn (Venue $venue): array => [
                (string) $venue->getKey() => $venue->optionLabel(),
            ])
            ->all();

        return $options;
    }

    private function singleVenueForTeam(Team $team): ?Venue
    {
        $venues = $team->venues()
            ->limit(2)
            ->get(self::VENUE_COLUMNS);

        return $venues->count() === 1 ? $venues->first() : null;
    }

    private function teamFor(mixed $teamId): ?Team
    {
        if ((! is_string($teamId) && ! is_int($teamId)) || blank($teamId)) {
            return null;
        }

        $team = $this->actor()->teams()->whereKey($teamId)->first();

        return $team instanceof Team ? $team : null;
    }

    private function copyVenueLocation(Set $set, Venue $venue): void
    {
        $set('location_name', $this->venuePlaceOfIncident($venue));
        $set('location_description', null);
    }

    private function venuePlaceOfIncident(Venue $venue): string
    {
        $address = implode(', ', $venue->location->addressArray());

        return filled($address)
            ? "{$venue->name}, {$address}"
            : $venue->name;
    }

    private function markLocationCustomized(): void
    {
        $this->locationWasCustomized = true;
    }

    private function actor(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
