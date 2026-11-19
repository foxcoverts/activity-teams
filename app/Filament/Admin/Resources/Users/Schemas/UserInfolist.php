<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Enums\AccountRole;
use App\Enums\AccountStatus;
use App\Enums\AgeGroup;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Filament\Schemas\ContactFields;
use App\Filament\Schemas\PersonalDataSection;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
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
                        TextEntry::make('name'),
                        TextEntry::make('timezone')
                            ->placeholder('-'),
                        TextEntry::make('status_badges')
                            ->hiddenLabel()
                            ->badge()
                            ->state(fn (User $record): array => static::statusBadges($record)),
                        PersonalDataSection::make(PersonalDataSectionEnum::ContactDetails, [
                            PersonalDataSection::emailEntry(__('Email address')),
                            ContactFields::phoneEntry('phone'),
                        ]),
                        PersonalDataSection::make(PersonalDataSectionEnum::EmergencyContact, [
                            TextEntry::make('emergency_name')
                                ->placeholder('-'),
                            ContactFields::phoneEntry('emergency_phone')
                                ->label(__('Emergency contact phone')),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return list<AccountStatus|MembershipRole|AccountRole|AgeGroup>
     */
    protected static function statusBadges(User $user): array
    {
        $badges = [];
        $status = $user->status();

        // Invitation status for placeholders lives on the Contact Details header.
        if (! $user->isPlaceholder() || in_array($status, [AccountStatus::Deleted, AccountStatus::Suspended], true)) {
            $badges[] = $status;
        }

        if ($user->isUnder18()) {
            $badges[] = AgeGroup::Under18;
        }

        if ($teamRole = $user->teamRole()) {
            $badges[] = $teamRole;
        }

        return [...$badges, ...$user->roles()];
    }
}
