<?php

namespace App\Actions;

use App\Filament\Venues\VenueLocationSchema;
use App\Models\Task;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveTaskDetailsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Team $team): Task
    {
        return DB::transaction(function () use ($data, $team): Task {
            $privateVenueData = $data['private_venue'] ?? null;
            unset($data['private_venue']);

            $placeholderVenue = null;

            if (is_array($privateVenueData)) {
                $this->assertPrivateVenueName($privateVenueData);
                $placeholderVenue = $this->createPlaceholderVenue($team);
                $data['venue_id'] = $placeholderVenue->getKey();
            }

            $task = new Task($data);
            $task->team_id = $team->getKey();
            $task->save();

            if ($placeholderVenue instanceof Venue && is_array($privateVenueData)) {
                $venue = $this->createPrivateVenue($privateVenueData, $task);
                $task->venue_id = $venue->getKey();
                $task->save();
                $team->venues()->detach($placeholderVenue->getKey());
                $placeholderVenue->deleteQuietly();
            }

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data): Task {
            $privateVenueData = $data['private_venue'] ?? null;
            unset($data['private_venue']);

            $previousPrivateVenue = Venue::onlyPrivate()
                ->where('task_id', $task->getKey())
                ->first();

            if (is_array($privateVenueData)) {
                $this->assertPrivateVenueName($privateVenueData);

                if ($previousPrivateVenue instanceof Venue) {
                    $venue = $previousPrivateVenue;
                    $venue->fill(Arr::only($privateVenueData, VenueLocationSchema::venueAttributes()));
                    $venue->save();
                } else {
                    $venue = $this->createPrivateVenue($privateVenueData, $task);
                }

                $data['venue_id'] = $venue->getKey();
            }

            $task->fill($data);
            $task->save();

            if (
                $previousPrivateVenue instanceof Venue
                && ! is_array($privateVenueData)
                && (string) $task->venue_id !== (string) $previousPrivateVenue->getKey()
            ) {
                $previousPrivateVenue->delete();
            }

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPrivateVenue(array $data, Task $task): Venue
    {
        $venue = new Venue(Arr::only($data, VenueLocationSchema::venueAttributes()));
        $venue->task_id = $task->getKey();
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
        $venue = new Venue(['name' => "Private task {$id}"]);
        $venue->id = $id;
        $venue->saveQuietly();
        $team->venues()->attach($venue->getKey());

        return $venue;
    }
}
