<?php

namespace App\Actions;

use App\Filament\Venues\VenueLocationSchema;
use App\Models\Booking;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveBookingDetailsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Team $team): Booking
    {
        return DB::transaction(function () use ($data, $team): Booking {
            $privateVenueData = $data['private_venue'] ?? null;
            unset($data['private_venue']);

            $placeholderVenue = null;

            if (is_array($privateVenueData)) {
                $this->assertPrivateVenueName($privateVenueData);
                $placeholderVenue = $this->createPlaceholderVenue($team);
                $data['venue_id'] = $placeholderVenue->getKey();
            }

            $booking = new Booking($data);
            $booking->team_id = $team->getKey();
            $booking->save();

            if ($placeholderVenue instanceof Venue && is_array($privateVenueData)) {
                $venue = $this->createPrivateVenue($privateVenueData, $booking);
                $booking->venue_id = $venue->getKey();
                $booking->save();
                $team->venues()->detach($placeholderVenue->getKey());
                $placeholderVenue->deleteQuietly();
            }

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            $privateVenueData = $data['private_venue'] ?? null;
            unset($data['private_venue']);

            $previousPrivateVenue = Venue::onlyPrivate()
                ->where('booking_id', $booking->getKey())
                ->first();
            $privateVenueChanged = false;

            if (is_array($privateVenueData)) {
                $this->assertPrivateVenueName($privateVenueData);

                if ($previousPrivateVenue instanceof Venue) {
                    $venue = $previousPrivateVenue;
                    $venue->fill(Arr::only($privateVenueData, VenueLocationSchema::venueAttributes()));
                    $privateVenueChanged = $venue->isDirty();
                    $venue->save();
                } else {
                    $venue = $this->createPrivateVenue($privateVenueData, $booking);
                }

                $data['venue_id'] = $venue->getKey();
            }

            $booking->fill($data);

            if (
                $privateVenueChanged
                && ! $booking->isDirty([
                    'activity_id',
                    'audience_id',
                    'activity_label',
                    'start_at',
                    'end_at',
                    'venue_id',
                    'status',
                ])
            ) {
                $booking->sequence++;
            }

            $booking->save();

            if (
                $previousPrivateVenue instanceof Venue
                && ! is_array($privateVenueData)
                && (string) $booking->venue_id !== (string) $previousPrivateVenue->getKey()
            ) {
                $previousPrivateVenue->delete();
            }

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPrivateVenue(array $data, Booking $booking): Venue
    {
        $venue = new Venue(Arr::only($data, VenueLocationSchema::venueAttributes()));
        $venue->booking_id = $booking->getKey();
        $venue->saveQuietly();

        return $venue;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPrivateVenueName(array $data): void
    {
        if (blank($data['name'] ?? null)) {
            throw ValidationException::withMessages([
                'name' => __('Enter a venue name.'),
            ]);
        }
    }

    private function createPlaceholderVenue(Team $team): Venue
    {
        $id = (string) Str::ulid();
        $venue = new Venue(['name' => "Private booking {$id}"]);
        $venue->id = $id;
        $venue->saveQuietly();
        $team->venues()->attach($venue->getKey());

        return $venue;
    }
}
