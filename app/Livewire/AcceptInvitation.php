<?php

namespace App\Livewire;

use App\Actions\AcceptTeamInvitationAction;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Member\Pages\Dashboard;
use App\Filament\Schemas\ContactFields;
use App\Models\TeamInvitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;

/**
 * @property-read Schema $form
 */
class AcceptInvitation extends SimplePage
{
    protected bool $hasTopbar = false;

    protected Width|string|null $maxWidth = Width::ExtraLarge;

    #[Locked]
    public string $invitationId = '';

    #[Locked]
    public string $invitationUrl = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public bool $declined = false;

    public function mount(TeamInvitation $invitation): void
    {
        Filament::setCurrentPanel(Filament::getPanel('member'));

        abort_unless(hash_equals($invitation->token, (string) request()->query('token')), 403);

        $this->invitationId = $invitation->getKey();
        $this->invitationUrl = request()->fullUrl();

        $this->form->fill([
            'name' => $invitation->user->name,
            'email' => $invitation->email,
            'timezone' => (string) (Auth::user()?->timezone ?? config('app.timezone')),
            'phone' => null,
            'emergency_name' => null,
            'emergency_phone' => null,
            'privacy_notice_accepted' => false,
        ]);

        // Lets the invitee return here after signing in with an existing account.
        session()->put('url.intended', request()->fullUrl());
    }

    public function getTitle(): string|Htmlable
    {
        return __('Invitation');
    }

    public function getHeading(): string|Htmlable
    {
        $invitation = $this->invitation();

        if ($invitation->isSiteInvitation()) {
            return __('Invitation to :app', ['app' => config('app.name')]);
        }

        return __('Invitation to :team', ['team' => $invitation->team->name]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $invitation = $this->invitation();

        if ($this->declined) {
            return $invitation->isSiteInvitation()
                ? __('You have declined this invitation. The system administrators have been told.')
                : __('You have declined this invitation. Your team manager has been told.');
        }

        if (! $this->isValid()) {
            return $invitation->isSiteInvitation()
                ? __('This invitation is no longer valid. Ask a system administrator to send you a new one.')
                : __('This invitation is no longer valid. Ask your team manager to send you a new one.');
        }

        if ($invitation->isSiteInvitation()) {
            return __('You have been invited as :roles.', ['roles' => $invitation->siteAccessPhrase()]);
        }

        return __('You have been invited to join :team.', [
            'team' => $invitation->team->name,
            'name' => $invitation->user->name,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Create an account'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email address'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('You can use a different address from the one invited. If you do, you will need to verify it.')),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->rule(Password::defaults())
                            ->same('passwordConfirmation')
                            ->validationAttribute(__('password')),
                        TextInput::make('passwordConfirmation')
                            ->label(__('Confirm password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->dehydrated(false),
                        TimezoneSelect::make('timezone')
                            ->required()
                            ->searchable(),
                    ]),
                Section::make(__('Contact details'))
                    ->icon(Heroicon::OutlinedPhone)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        ContactFields::phoneNotice(),
                        ContactFields::phoneInput('phone'),
                    ]),
                Section::make(__('Emergency contact'))
                    ->icon(Heroicon::OutlinedHeart)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(ContactFields::emergencyFields()),
                ContactFields::privacyNoticeLink(),
                ContactFields::privacyNoticeAcknowledgement(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->isValid()) {
            return $schema->components([]);
        }

        $account = Auth::user();

        if ($account instanceof User) {
            return $schema->components([
                Section::make(__('Use your existing account'))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Text::make(
                            $this->invitation()->isSiteInvitation()
                                ? __('You are signed in as :name. Accepting adds this access to your account.', ['name' => $account->name])
                                : __('You are signed in as :name. Accepting adds this team to your account.', ['name' => $account->name])
                        ),
                        ContactFields::privacyNoticeLink(),
                        Actions::make([
                            Action::make('useMyAccount')
                                ->label(__('Accept with this account'))
                                ->color(Color::Sky)
                                ->size(Size::Large)
                                ->action('useMyAccount'),
                            Action::make('signOutAndCreateAccount')
                                ->label(__('Sign out and create a new account'))
                                ->color('gray')
                                ->size(Size::Large)
                                ->action('signOutAndCreateAccount'),
                        ])->alignment(Alignment::Start),
                    ]),
                Actions::make([
                    Action::make('decline')
                        ->label(__('Decline invitation'))
                        ->color('gray')
                        ->size(Size::Large)
                        ->requiresConfirmation()
                        ->modalHeading(__('Decline this invitation?'))
                        ->action('decline'),
                ])->alignment(Alignment::Start),
            ]);
        }

        $loginUrl = e(route('filament.member.auth.login'));

        return $schema->components([
            Text::make(new HtmlString(
                '<span class="font-semibold">'.e(__('Already have an account?')).'</span> '.
                '<a class="underline" href="'.$loginUrl.'">'.e(__('Sign in to accept with your existing account')).'</a>'
            )),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('createAccount')
                ->footer([
                    Actions::make([
                        Action::make('createAccount')
                            ->label(__('Create account'))
                            ->submit('createAccount')
                            ->size(Size::Large)
                            ->color(Color::Sky),
                    ])->alignment(Alignment::Start),
                ]),
            Actions::make([
                Action::make('decline')
                    ->label(__('Decline invitation'))
                    ->color('gray')
                    ->size(Size::Large)
                    ->requiresConfirmation()
                    ->modalHeading(__('Decline this invitation?'))
                    ->action('decline'),
            ])->alignment(Alignment::Start),
        ]);
    }

    public function createAccount(AcceptTeamInvitationAction $accept): void
    {
        $data = $this->form->getState();

        $user = $accept->createAccount($this->invitation(), $data);

        Auth::login($user);

        $this->redirect(Dashboard::getUrl(panel: 'member'));
    }

    public function useMyAccount(AcceptTeamInvitationAction $accept): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $accept->claimWith($this->invitation(), $user);

        $this->redirect(Dashboard::getUrl(panel: 'member'));
    }

    public function signOutAndCreateAccount(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect($this->invitationUrl);
    }

    public function decline(AcceptTeamInvitationAction $accept): void
    {
        $accept->decline($this->invitation());

        $this->declined = true;
    }

    private function isValid(): bool
    {
        return $this->invitation()->isPending() && ! $this->declined;
    }

    private function invitation(): TeamInvitation
    {
        return TeamInvitation::query()->with(['team', 'user'])->findOrFail($this->invitationId);
    }
}
