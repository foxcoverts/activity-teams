<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class CalendarLinks extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected string $view = 'filament.member.pages.calendar-links';

    protected static ?string $navigationLabel = 'Calendar feeds';

    public static function getNavigationLabel(): string
    {
        return __('Calendar feeds');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Calendar feeds');
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();
        if (blank($user->ical_token)) {
            $user->forceFill(['ical_token' => User::generateToken()])->save();
        }
    }

    public function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user()->fresh();

        return [
            'bookingsUrl' => route('booking.ics', $user),
            'rotaUrl' => route('booking.rota.ics', $user),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label(__('Regenerate links'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color(Palette::destructive())
                ->requiresConfirmation()
                ->modalHeading(__('Regenerate calendar links'))
                ->modalDescription(__('Existing calendar subscriptions will stop working until you add the new links.'))
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $user->forceFill(['ical_token' => User::generateToken()])->save();

                    Notification::make()
                        ->success()
                        ->title(__('Calendar links updated'))
                        ->send();
                }),
        ];
    }
}
