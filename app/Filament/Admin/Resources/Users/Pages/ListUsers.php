<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Actions\SendTeamInvitationAction;
use App\Enums\Section;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label('Add a person')
                ->visible(fn (): bool => UserResource::canInvite())
                ->modalDescription(__('They are added without a team and sent an invitation to take over this name with their own account.'))
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email address to invite')
                        ->email()
                        ->required(),
                    Select::make('section')
                        ->options(Section::class)
                        ->default(Section::Adult)
                        ->required(),
                    Toggle::make('oversight'),
                    Toggle::make('is_system_admin')
                        ->label('System admin'),
                ])
                ->action(function (array $data, Schema $schema): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        app(SendTeamInvitationAction::class)->addAdmin(
                            $actor,
                            $data['name'],
                            $data['email'],
                            (bool) ($data['oversight'] ?? false),
                            (bool) ($data['is_system_admin'] ?? false),
                            $data['section'] instanceof Section
                                ? $data['section']
                                : Section::from((string) ($data['section']->value ?? $data['section'])),
                        );
                    } catch (ValidationException $exception) {
                        UserResource::reportOnField($exception, $schema, collect($exception->errors())->keys()->first() ?? 'email');
                    }

                    Notification::make()->success()->title(__('Person added and invitation sent'))->send();
                }),
        ];
    }
}
