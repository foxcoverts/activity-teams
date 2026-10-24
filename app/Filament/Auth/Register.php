<?php

namespace App\Filament\Auth;

use App\Enums\MembershipRole;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Schemas\ContactFields;
use App\Models\Team;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class Register extends BaseRegister
{
    public function mount(): void
    {
        abort_unless(User::query()->count() === 0, Response::HTTP_FORBIDDEN, __('This site is invite only.'));

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                TimezoneSelect::make('timezone')
                    ->required()
                    ->searchable()
                    ->default(config('app.timezone')),
                ContactFields::phoneNotice(),
                ContactFields::phoneInput('phone'),
                ...ContactFields::emergencyFields(),
                ContactFields::privacyNoticeLink(),
                ContactFields::privacyNoticeAcknowledgement(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['privacy_notice_accepted_at'] = now();
        $data['profile_confirmed_at'] = now();

        return $data;
    }

    protected function handleRegistration(array $data): Model
    {
        /** @var User $user */
        $user = parent::handleRegistration($data);
        $user->is_system_admin = true;
        $user->save();

        $team = Team::query()->create([
            'name' => __('Team'),
        ]);
        $team->grant($user, MembershipRole::Manager);

        return $user;
    }
}
