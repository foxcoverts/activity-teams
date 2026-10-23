<?php

namespace App\Filament\Manage\Actions;

use App\Actions\SetSuspensionAction;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Deliberately tucked into a "More actions" menu on edit pages: suspension is rare and serious.
 */
class SuspensionActions
{
    public static function list(User $member): array
    {
        return [
            static::suspend($member),
            static::restore($member),
        ];
    }

    public static function suspend(User $member): Action
    {
        return Action::make('suspend')
            ->label(__('Suspend'))
            ->icon(Heroicon::NoSymbol)
            ->color(Palette::destructive())
            ->visible(fn (): bool => ! $member->isSuspended() && (auth()->user()?->can('suspend', $member) ?? false))
            ->requiresConfirmation()
            ->modalHeading(__('Suspend :name?', ['name' => $member->name]))
            ->modalDescription(fn (): string => static::suspendWarning($member))
            ->modalSubmitActionLabel(__('Suspend'))
            ->action(function () use ($member): void {
                app(SetSuspensionAction::class)(auth()->user(), $member, true);

                Notification::make()->success()->title(__('Suspended'))->send();
            });
    }

    public static function restore(User $member): Action
    {
        return Action::make('restore')
            ->label(__('Restore'))
            ->icon(Heroicon::ArrowUturnLeft)
            ->visible(fn (): bool => $member->isSuspended() && (auth()->user()?->can('suspend', $member) ?? false))
            ->requiresConfirmation()
            ->modalHeading(__('Restore :name?', ['name' => $member->name]))
            ->modalDescription(__('They will regain access to their existing account, teams and bookings.'))
            ->modalSubmitActionLabel(__('Restore'))
            ->action(function () use ($member): void {
                app(SetSuspensionAction::class)(auth()->user(), $member, false);

                Notification::make()->success()->title(__('Restored'))->send();
            });
    }

    protected static function suspendWarning(User $member): string
    {
        $warning = __('They will lose access in every team and be sent nothing about bookings or other activity. Their memberships, profile and attendance are kept.');
        $led = $member->ledBookings()->notCancelled()->future()->count();

        if ($led > 0) {
            $warning .= ' '.trans_choice(
                'They are lead instructor for :count upcoming booking, which will be left without a lead and return to tentative.|They are lead instructor for :count upcoming bookings, which will be left without a lead and return to tentative.',
                $led,
            );
        }

        $teams = $member->teamsLeftWithoutActiveManager();

        if ($teams->isEmpty()) {
            return $warning;
        }

        return $warning.' '.__('Warning: :teams would have no active manager.', [
            'teams' => $teams->map(fn (Team $team): string => $team->name)->join(', '),
        ]);
    }
}
