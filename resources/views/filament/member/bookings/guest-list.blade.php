@livewire(\App\Livewire\SessionRoster::class, [
    'booking' => $booking,
    'showHeading' => false,
], 'member-session-roster-'.$booking->getKey())
