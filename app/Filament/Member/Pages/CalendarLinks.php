<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use Filament\Pages\Page;
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
}
