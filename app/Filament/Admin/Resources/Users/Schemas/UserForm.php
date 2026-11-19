<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Schemas\ContactFields;
use App\Filament\Schemas\PersonalDataSection;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('User'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Toggle::make('under_18')
                            ->label(__('Under 18'))
                            ->default(false),
                        TimezoneSelect::make('timezone')
                            ->searchable(),
                        Placeholder::make('password')
                            ->label(__('Password'))
                            ->content(__('Only the user can change their own password.'))
                            ->hiddenOn('view'),
                        PersonalDataSection::form(PersonalDataSectionEnum::ContactDetails, [
                            ...PersonalDataSection::emailFields(__('Email address')),
                            ContactFields::phoneInput('phone'),
                        ]),
                        PersonalDataSection::form(PersonalDataSectionEnum::EmergencyContact, [
                            TextInput::make('emergency_name'),
                            ContactFields::phoneInput('emergency_phone')
                                ->label(__('Emergency contact phone')),
                        ]),
                    ]),
                Section::make(__('Access'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columns(1)
                    ->columnSpanFull()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSystemAdmin())
                    ->schema([
                        Toggle::make('oversight'),
                        Toggle::make('is_system_admin')
                            ->label(__('System admin')),
                    ]),
            ]);
    }
}
