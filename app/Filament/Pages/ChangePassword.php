<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

/**
 * @property-read Schema $form
 */
class ChangePassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'change-password';

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getTitle(): string
    {
        return __('Change password');
    }

    public static function userMenuItem(): Action
    {
        return Action::make('changePassword')
            ->label(__('Change password'))
            ->icon(Heroicon::OutlinedKey)
            ->url(fn (): string => static::getUrl(panel: 'member'));
    }

    public function mount(): void
    {
        $this->form->fill();
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
                Section::make()
                    ->schema([
                        TextInput::make('currentPassword')
                            ->label(__('Current password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->currentPassword(guard: Filament::getAuthGuard())
                            ->required()
                            ->dehydrated(false),
                        TextInput::make('password')
                            ->label(__('New password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->rule(Password::default())
                            ->required()
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label(__('Confirm new password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Change password'))
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Filament::auth()->user();

        assert($user instanceof User);

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
            ]);
        }

        $this->form->fill();

        Notification::make()
            ->success()
            ->title(__('Password changed'))
            ->send();
    }
}
