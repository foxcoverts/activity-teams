<?php

use App\Http\Controllers\BookingIcsController;
use App\Http\Controllers\LogbookExportController;
use App\Http\Controllers\NewsFeedController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\TaskIcsController;
use App\Http\Controllers\UnsubscribeMailController;
use App\Http\Middleware\AuthenticateFromParam;
use App\Livewire\AcceptInvitation;
use App\Livewire\BookingPreview;
use App\Livewire\ConfirmProfile;
use App\Livewire\ManageMailPreferences;
use App\Livewire\RespondToBooking;
use App\Livewire\RespondToTask;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('share/bookings/{booking}', BookingPreview::class)->name('booking.preview');
Route::get('respond/{booking}/{attendee}', RespondToBooking::class)->name('respond');
Route::get('respond-task/{task}/{assignee}', RespondToTask::class)->name('respond-task');
Route::get('mail-preferences/{user:mail_preference_token}/booking/{booking}', ManageMailPreferences::class)
    ->name('mail-preferences.booking')
    ->missing(fn () => abort(403));
Route::get('mail-preferences/{user:mail_preference_token}/{group}', ManageMailPreferences::class)
    ->name('mail-preferences.group')
    ->missing(fn () => abort(403));
Route::match(['get', 'post'], 'mail-preferences/{user}/unsubscribe/{group}', UnsubscribeMailController::class)
    ->middleware('signed')
    ->name('mail-preferences.unsubscribe');
Route::get('invitation/{invitation}', AcceptInvitation::class)->middleware('signed')->name('invitation.show');
Route::get('privacy', PrivacyPolicyController::class)->name('privacy');
Route::get('account-deleted', fn () => view('account-deleted'))->name('account.deleted');
Route::middleware('auth')->get('profile/confirm', ConfirmProfile::class)->name('profile.confirm');

Route::middleware(AuthenticateFromParam::class.':user')->group(function () {
    $missingToken = fn () => abort(401);

    Route::get('ical/{user:feed_token}/bookings.ics', [BookingIcsController::class, 'index'])
        ->name('booking.ics')
        ->missing($missingToken);
    Route::get('ical/{user:feed_token}/rota.ics', [BookingIcsController::class, 'rota'])
        ->name('booking.rota.ics')
        ->missing($missingToken);
    Route::get('ical/{user:feed_token}/tasks.ics', [TaskIcsController::class, 'tasks'])
        ->name('task.ics')
        ->missing($missingToken);
    Route::get('ical/{user:feed_token}/everything.ics', [TaskIcsController::class, 'everything'])
        ->name('calendar.everything.ics')
        ->missing($missingToken);
    Route::get('feeds/{user:feed_token}/news.atom', [NewsFeedController::class, 'atom'])
        ->name('news.atom')
        ->missing($missingToken);
    Route::get('feeds/{user:feed_token}/news.rss', [NewsFeedController::class, 'rss'])
        ->name('news.rss')
        ->missing($missingToken);
});

Route::middleware('auth')->get('bookings/{booking}.ics', [BookingIcsController::class, 'show'])->name('booking.show.ics');
Route::middleware('auth')->get('tasks/{task}.ics', [TaskIcsController::class, 'show'])->name('task.show.ics');
Route::middleware('auth')->get('logbook/export', [LogbookExportController::class, 'pdf'])->name('member.logbook.export');
