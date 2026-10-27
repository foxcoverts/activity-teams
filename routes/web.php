<?php

use App\Http\Controllers\BookingIcsController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Middleware\AuthenticateFromParam;
use App\Livewire\AcceptInvitation;
use App\Livewire\BookingPreview;
use App\Livewire\ConfirmProfile;
use App\Livewire\ManageMailPreferences;
use App\Livewire\RespondToBooking;
use Illuminate\Support\Facades\Route;

Route::get('share/bookings/{booking}', BookingPreview::class)->name('booking.preview');
Route::get('respond/{booking}/{attendee}', RespondToBooking::class)->name('respond');
Route::get('mail-preferences/{user:mail_preference_token}/booking/{booking}', ManageMailPreferences::class)
    ->name('mail-preferences.booking')
    ->missing(fn () => abort(403));
Route::get('mail-preferences/{user:mail_preference_token}/{group}', ManageMailPreferences::class)
    ->name('mail-preferences.group')
    ->missing(fn () => abort(403));
Route::get('invitation/{invitation}', AcceptInvitation::class)->middleware('signed')->name('invitation.show');
Route::get('privacy', PrivacyPolicyController::class)->name('privacy');
Route::get('account-deleted', fn () => view('account-deleted'))->name('account.deleted');
Route::middleware('auth')->get('profile/confirm', ConfirmProfile::class)->name('profile.confirm');

Route::middleware(AuthenticateFromParam::class.':user')->group(function () {
    Route::get('ical/{user:ical_token}/bookings.ics', [BookingIcsController::class, 'index'])->name('booking.ics');
    Route::get('ical/{user:ical_token}/rota.ics', [BookingIcsController::class, 'rota'])->name('booking.rota.ics');
});

Route::middleware('auth')->get('bookings/{booking}.ics', [BookingIcsController::class, 'show'])->name('booking.show.ics');
