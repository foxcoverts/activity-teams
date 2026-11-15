<?php

use App\Filament\Member\Resources\Keys\KeyResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Booking;
use App\Models\Key;
use App\Models\NewsPost;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::permanentRedirect('privacy-policy', '/privacy');

Route::get('booking/{booking}.ics', function (Booking $booking) {
    return redirect()->route('booking.show.ics', $booking, 301);
});

Route::get('booking/{booking}/preview', function (Booking $booking) {
    $booking->loadMissing('team');

    return redirect(
        BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $booking->team),
        301,
    );
});

Route::get('booking/{booking}', function (Booking $booking) {
    $booking->loadMissing('team');

    return redirect(
        BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $booking->team),
        301,
    );
});

Route::get('news/{post}', function (string $post) {
    $newsPost = NewsPost::query()->where('slug', $post)->with('team')->firstOrFail();

    return redirect(
        NewsPostResource::getUrl('view', ['record' => $newsPost], panel: 'teams', tenant: $newsPost->team),
        301,
    );
});

Route::get('todo/{todo}', function (Task $todo) {
    $todo->loadMissing('team');

    return redirect(
        TaskResource::getUrl('view', ['record' => $todo], panel: 'teams', tenant: $todo->team),
        301,
    );
});

Route::get('key/{key}', function (Key $key) {
    return redirect(
        KeyResource::getUrl('view', ['record' => $key], panel: 'member'),
        301,
    );
});

Route::get('ical/{user:feed_token}/booking.ics', function (User $user) {
    return redirect()->route('booking.ics', $user, 301);
})->missing(fn () => abort(401));
