<?php

namespace App\Livewire;

use App\Filament\Schemas\ContactFields;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * @property-read Schema $form
 */
class ConfirmProfile extends SimplePage
{
    protected bool $hasTopbar = false;

    protected Width|string|null $maxWidth = Width::ExtraLarge;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('member'));

        $user = $this->user();

        abort_unless($user->profileConfirmationIsDue(), 403);

        $user->refresh();

        $this->form->fill([
            'email' => $user->email,
            'phone' => $user->phone,
            'emergency_name' => $user->emergency_name,
            'emergency_phone' => $user->emergency_phone,
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        return __('Confirm your contact details');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $url = e(ContactFields::privacyNoticeUrl());
        $label = e(__('Privacy notice'));

        return new HtmlString(
            e(__('Please review your email, phone, and emergency contact. You can change them here before confirming.')).
            '<br><a class="underline" href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$label.'</a>'
        );
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model($this->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label(__('Email address'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, ignoreRecord: true),
                Section::make(__('Contact details'))
                    ->description(ContactFields::phoneNoticeCopy())
                    ->schema([
                        ContactFields::phoneInput('phone'),
                    ]),
                Section::make(__('Emergency contact'))
                    ->description(ContactFields::emergencyNoticeCopy())
                    ->schema([
                        TextInput::make('emergency_name')
                            ->maxLength(100),
                        ContactFields::phoneInput('emergency_phone')
                            ->label(__('Emergency contact phone')),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('confirm')
                    ->footer([
                        Actions::make([
                            Action::make('confirm')
                                ->label(__('Confirm'))
                                ->submit('confirm')
                                ->size(Size::Large)
                                ->color(Color::Sky),
                            Action::make('defer')
                                ->label(__('Defer'))
                                ->size(Size::Large)
                                ->color('gray')
                                ->action('deferConfirmation'),
                        ])->alignment(Alignment::Start),
                    ]),
            ]);
    }

    public function confirm(): void
    {
        $user = $this->user();
        $data = $this->form->getState();
        $emailChanged = $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->profile_confirmed_at = now();
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        $this->redirectIntendedHome();
    }

    public function deferConfirmation(): void
    {
        $this->user()->deferProfileConfirmation();

        $this->redirectIntendedHome();
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function redirectIntendedHome(): void
    {
        $url = Filament::getPanel('member')?->getUrl() ?? '/';

        $this->redirect($url);
    }
}
