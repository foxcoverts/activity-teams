<?php

namespace App\Livewire;

use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BookingPreview extends Component
{
    protected Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load(['activity', 'venue']);

        if (auth()->guest()) {
            redirect()->setIntendedUrl($this->bookingUrl());
        }
    }

    public function heading(): string
    {
        return __(':activity on :date', [
            'activity' => $this->booking->activity->name,
            'date' => $this->localStart()->toFormattedDayDateString(),
        ]);
    }

    public function description(): string
    {
        $start = $this->localStart();
        $end = $this->localEnd();
        $sameDay = $start->isSameDay($end);

        return __(':activity from :start to :end at :venue.', [
            'activity' => $this->booking->activity->name,
            'start' => $sameDay ? $start->isoFormat('HH:mm') : $start->isoFormat('ddd D MMM YYYY HH:mm'),
            'end' => $sameDay ? $end->isoFormat('HH:mm') : $end->isoFormat('ddd D MMM YYYY HH:mm'),
            'venue' => $this->booking->venue->name,
        ]);
    }

    public function imageUrl(): string
    {
        $start = $this->localStart();

        return asset('images/dates/'.$start->month.'/'.$start->month.'-'.$start->day.'.png');
    }

    public function bookingUrl(): string
    {
        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'member');
    }

    public function loginUrl(): string
    {
        return route('filament.member.auth.login');
    }

    public function render(): View
    {
        return view('livewire.booking-preview', [
            'booking' => $this->booking,
        ])
            ->title($this->heading())
            ->layoutData([
                'description' => $this->description(),
                'url' => $this->bookingUrl(),
                'image' => $this->imageUrl(),
            ]);
    }

    private function localStart(): Carbon
    {
        return localDate($this->booking->start_at, $this->booking->timezone);
    }

    private function localEnd(): Carbon
    {
        return localDate($this->booking->end_at, $this->booking->timezone);
    }
}
