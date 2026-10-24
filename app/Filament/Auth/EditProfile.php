<?php

namespace App\Filament\Auth;

use App\Actions\DeleteUserAction;
use App\Actions\ExportPersonalDataAction;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Schemas\ContactFields;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return __('Profile');
    }

    public static function userMenuItem(): Action
    {
        return Action::make('profile')
            ->label(fn (): string => static::getLabel())
            ->icon(Heroicon::UserCircle)
            ->url(fn (): string => static::getUrl(panel: 'member'))
            ->sort(-1);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Personal details'))
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        TimezoneSelect::make('timezone')
                            ->searchable(),
                    ]),
                Section::make(__('Contact details'))
                    ->schema([
                        ContactFields::phoneNotice(),
                        ContactFields::phoneInput('phone'),
                    ]),
                Section::make(__('Emergency contact'))
                    ->schema(ContactFields::emergencyFields()),
                ContactFields::privacyNoticeLink(),
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $hint = ContactFields::emailVerifiedHint($user);

        return parent::getEmailFormComponent()
            ->hintIcon($hint['icon'])
            ->hintColor($hint['color'])
            ->hint($hint['hint']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        assert($record instanceof User);

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $record->email;

        $record->fill($data);

        if ($emailChanged) {
            $record->email_verified_at = null;
        }

        $record->profile_confirmed_at = now();
        $record->save();

        if ($emailChanged) {
            $record->sendEmailVerificationNotification();
        }

        return $record;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment($this->getFormActionsAlignment())
                            ->key('form-actions'),
                    ]),
                Actions::make([
                    $this->downloadDataAction(),
                    $this->deleteAccountAction(),
                ]),
            ]);
    }

    protected function downloadDataAction(): Action
    {
        return Action::make('downloadData')
            ->label(__('Download my data'))
            ->action(function (): StreamedResponse {
                $user = $this->getUser();
                assert($user instanceof User);
                $payload = app(ExportPersonalDataAction::class)->export($user);

                return response()->streamDownload(function () use ($payload): void {
                    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }, 'personal-data.json', ['Content-Type' => 'application/json']);
            });
    }

    protected function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label(__('Delete account'))
            ->color('danger')
            ->schema([
                TextInput::make('password')
                    ->label(__('Current password'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->modalHeading(__('Delete your account'))
            ->modalDescription(__('Your name, qualifications, past attendance history will be kept. Contact details, credentials, tokens, and preferences are removed immediately or after 30 days. You will not be able to sign in after you confirm.'))
            ->modalSubmitActionLabel(__('Delete account'))
            ->visible(function (): bool {
                $user = $this->getUser();

                return $user instanceof User && ! $user->isLastSystemAdmin();
            })
            ->action(function (array $data): void {
                $user = $this->getUser();
                assert($user instanceof User);

                try {
                    app(DeleteUserAction::class)->delete($user, $user, $data['password'] ?? null);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('This account cannot be deleted.'))
                        ->send();

                    return;
                }

                Filament::auth()->logout();
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                $this->redirect(route('account.deleted'));
            });
    }
}
