<?php

use App\Http\Controllers\BookingIcsController;
use App\Http\Middleware\AuthenticateFromParam;
use App\Livewire\BookingPreview;
use App\Livewire\RespondToBooking;
use App\Livewire\SetupAccount;
use Illuminate\Support\Facades\Route;

Route::get('share/bookings/{booking}', BookingPreview::class)->name('booking.preview');
Route::get('respond/{booking}/{attendee}', RespondToBooking::class)->name('respond');
Route::get('setup-account/{user}', SetupAccount::class)->middleware('signed')->name('setup-account');

Route::middleware(AuthenticateFromParam::class.':user')->group(function () {
    Route::get('ical/{user:ical_token}/bookings.ics', [BookingIcsController::class, 'index'])->name('booking.ics');
    Route::get('ical/{user:ical_token}/rota.ics', [BookingIcsController::class, 'rota'])->name('booking.rota.ics');
});

Route::middleware('auth')->get('bookings/{booking}.ics', [BookingIcsController::class, 'show'])->name('booking.show.ics');
