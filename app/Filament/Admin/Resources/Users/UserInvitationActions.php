<?php

namespace App\Filament\Admin\Resources\Users;

use App\Actions\SendTeamInvitationAction;
use App\Enums\InvitationStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

class UserInvitationActions
{
    public static function group(?User $record = null): ActionGroup
    {
        return ActionGroup::make([
            static::send($record),
            static::revoke($record),
        ])
            ->record($record)
            ->label(__('Invitation'))
            ->button()
            ->color('gray')
            ->visible(fn (User $record): bool => static::canManage($record));
    }

    public static function send(?User $record = null): Action
    {
        return Action::make('sendInvitation')
            ->record($record)
            ->label(fn (User $record): string => match ($record->invitationStatus()) {
                InvitationStatus::Invited, InvitationStatus::Expired => __('Resend invitation'),
                InvitationStatus::Declined => __('Invite again'),
                default => __('Send invitation'),
            })
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading(fn (User $record): string => match ($record->invitationStatus()) {
                InvitationStatus::Invited, InvitationStatus::Expired => __('Resend invitation'),
                InvitationStatus::Declined => __('Invite again'),
                default => __('Send invitation'),
            })
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->visible(fn (User $record): bool => static::canManage($record))
            ->modalDescription(fn (User $record): ?string => $record->invitationStatus() === InvitationStatus::Declined
                ? __('This person declined the invitation. Check the email address is correct before inviting them again.')
                : null)
            ->fillForm(fn (User $record): array => ['email' => $record->invitation?->email])
            ->schema([
                TextInput::make('email')
                    ->label(__('Email address to invite'))
                    ->email()
                    ->required(),
            ])
            ->action(function (User $record, array $data, Schema $schema, LivewireComponent $livewire): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    app(SendTeamInvitationAction::class)->sendAdmin($actor, $record, $data['email']);
                } catch (ValidationException $exception) {
                    static::reportOnField($exception, $schema, 'email');
                }

                $livewire->dispatch('refresh-sidebar');
                $livewire->dispatch('refresh-topbar');

                Notification::make()->success()->title(__('Invitation sent'))->send();
            });
    }

    public static function revoke(?User $record = null): Action
    {
        return Action::make('revokeInvitation')
            ->record($record)
            ->label(__('Revoke invitation'))
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => static::canManage($record) && $record->invitationStatus() === InvitationStatus::Invited)
            ->action(function (User $record, LivewireComponent $livewire): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(SendTeamInvitationAction::class)->revokeAdmin($actor, $record);

                $livewire->dispatch('refresh-sidebar');
                $livewire->dispatch('refresh-topbar');

                Notification::make()->success()->title(__('Invitation revoked'))->send();
            });
    }

    public static function reportOnField(ValidationException $exception, Schema $schema, string $field): never
    {
        $component = $schema->getFlatComponents(withHidden: true)[$field] ?? null;
        $path = $component instanceof Component ? $component->getStatePath() : $field;

        throw ValidationException::withMessages([
            (string) $path => static::validationMessage($exception),
        ]);
    }

    public static function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : __('The invitation could not be sent.');
    }

    private static function canManage(User $record): bool
    {
        return UserResource::canInvite()
            && $record->isPlaceholder()
            && ! $record->isSuspended()
            && ! $record->teams()->exists();
    }
}
