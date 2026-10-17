<?php

namespace App\Livewire;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BookingPreview extends Component
{
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load(['activity', 'venue']);
    }

    public function render(): View
    {
        return view('livewire.booking-preview')
            ->title($this->booking->group_name);
    }
}
