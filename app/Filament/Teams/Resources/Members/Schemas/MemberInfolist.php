<?php

namespace App\Filament\Teams\Resources\Members\Schemas;

use App\Enums\AccountStatus;
use App\Enums\AgeGroup;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Filament\Schemas\ContactFields;
use App\Filament\Schemas\PersonalDataSection;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MemberInfolist
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
                        TextEntry::make('name'),
                        TextEntry::make('membership_role')
                            ->label('Role')
                            ->badge()
                            ->state(fn (User $record): ?MembershipRole => MemberResource::roleOnCurrentTeam($record)),
                        TextEntry::make('invited_email')
                            ->state(fn (User $record): ?string => $record->invitation?->email)
                            ->visible(fn (User $record): bool => $record->isPlaceholder() && $record->invitation !== null),
                        TextEntry::make('status_badges')
                            ->hiddenLabel()
                            ->badge()
                            ->state(fn (User $record): array => static::statusBadges($record)),
                        PersonalDataSection::make(PersonalDataSectionEnum::ContactDetails, [
                            TextEntry::make('email'),
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
     * @return list<AccountStatus|AgeGroup>
     */
    protected static function statusBadges(User $user): array
    {
        $badges = [$user->status()];

        if ($user->isUnder18()) {
            $badges[] = AgeGroup::Under18;
        }

        return $badges;
    }
}
