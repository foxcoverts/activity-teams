<?php

namespace App\Filament\Teams\Resources\Members\Schemas;

use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Filament\Schemas\ContactFields;
use App\Filament\Schemas\PersonalDataSection;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Member'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Toggle::make('under_18')
                            ->label(__('Under 18'))
                            ->default(false),
                        Placeholder::make('role')
                            ->label(__('Role'))
                            ->badge()
                            ->state(fn (User $record): ?MembershipRole => MemberResource::roleOnCurrentTeam($record)),
                        PersonalDataSection::form(PersonalDataSectionEnum::ContactDetails, [
                            TextInput::make('email')
                                ->email()
                                ->required(fn (?User $record): bool => ! $record?->isPlaceholder())
                                ->disabled(fn (?User $record): bool => $record?->isPlaceholder() ?? false),
                            ContactFields::phoneInput('phone'),
                        ]),
                        PersonalDataSection::form(PersonalDataSectionEnum::EmergencyContact, [
                            TextInput::make('emergency_name'),
                            ContactFields::phoneInput('emergency_phone')
                                ->label(__('Emergency contact phone')),
                        ]),
                    ]),
            ]);
    }
}
