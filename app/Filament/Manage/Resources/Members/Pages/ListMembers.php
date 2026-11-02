<?php

namespace App\Filament\Manage\Resources\Members\Pages;

use App\Actions\SendTeamInvitationAction;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label('Add a member')
                ->visible(fn (): bool => MemberResource::canInvite())
                ->modalDescription(__('They are added to your team straight away and sent an invitation to take over this name with their own account.'))
                ->schema([
                    Section::make(__('Member'))
                        ->icon(Heroicon::User)
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email address to invite')
                                ->email()
                                ->required(),
                            ToggleButtons::make('role')
                                ->options(MembershipRole::class)
                                ->default(MembershipRole::Member)
                                ->inline()
                                ->required(),
                            Toggle::make('under_18')
                                ->label(__('Under 18'))
                                ->default(false),
                        ]),
                ])
                ->action(function (array $data, Schema $schema): void {
                    try {
                        app(SendTeamInvitationAction::class)->add(
                            auth()->user(),
                            MemberResource::currentTeam(),
                            $data['name'],
                            $data['email'],
                            MembershipRole::from((string) ($data['role']->value ?? $data['role'])),
                            (bool) ($data['under_18'] ?? false),
                        );
                    } catch (ValidationException $exception) {
                        MemberResource::reportOnField($exception, $schema, 'email');
                    }

                    Notification::make()->success()->title(__('Member added and invitation sent'))->send();
                }),
        ];
    }
}
