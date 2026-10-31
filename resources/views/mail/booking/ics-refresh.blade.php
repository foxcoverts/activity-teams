<x-mail::message>
# {{ $cancelled ? __('Booking cancelled') : __('Updated calendar invitation') }}

{{ __('Hello :name,', ['name' => $attendee->name]) }}

@if ($cancelled)
{{ __('Here is the current cancellation for :activity for :audience at :venue.', [
    'activity' => $booking->activity->name,
    'audience' => $booking->audience?->name,
    'venue' => $booking->venue->name,
]) }}
@else
{{ __('Here is the current invitation for :activity for :audience at :venue.', [
    'activity' => $booking->activity->name,
    'audience' => $booking->audience?->name,
    'venue' => $booking->venue->name,
]) }}
@endif

{{ __('Your calendar asked for an update, or replied to an older version of this booking.') }}

@include('mail.partials.preferences-footer')
</x-mail::message>
