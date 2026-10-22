@include('filament.bookings.guest-list')

@if ($showAccessDetails)
    @include('filament.bookings.person-access', ['access' => $access])
@endif
