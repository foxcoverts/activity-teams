<?php

namespace App\Providers;

use App\Events\BookingCancelled;
use App\Events\BookingChanged;
use App\Events\BookingConfirmed;
use App\Events\BookingInvite;
use App\Events\BookingRestored;
use App\Listeners\SendBookingCancelledEmail;
use App\Listeners\SendBookingChangedEmail;
use App\Listeners\SendBookingConfirmedEmail;
use App\Listeners\SendBookingInviteEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::listen(BookingInvite::class, SendBookingInviteEmail::class);
        Event::listen(BookingConfirmed::class, SendBookingConfirmedEmail::class);
        Event::listen(BookingCancelled::class, SendBookingCancelledEmail::class);
        Event::listen(BookingChanged::class, SendBookingChangedEmail::class);
        Event::listen(BookingRestored::class, SendBookingChangedEmail::class);
    }
}
