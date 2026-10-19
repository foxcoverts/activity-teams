@include('filament.bookings.person-access', [
    'alpine' => true,
    'access' => \App\Support\BookingPeople::panel($booking, $person, $panel),
])
