<?php

namespace App\Actions;

use App\Filament\Venues\VenueLocationSchema;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveTaskDetailsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Team $team, ?string $assigneeId = null, bool $invite = true): Task
    {
        return DB::transaction(function () use ($data, $team, $assigneeId, $invite): Task {
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

            $this->syncAssignee($task, $assigneeId, $invite);

            return $task->fresh(['attendance.user', 'venue']) ?? $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, ?string $assigneeId = null, bool $syncAssignee = false, bool $invite = true): Task
    {
        return DB::transaction(function () use ($task, $data, $assigneeId, $syncAssignee, $invite): Task {
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
            $changedAttributes = array_keys($task->getDirty());
            $task->save();

            if (
                $previousPrivateVenue instanceof Venue
                && ! is_array($privateVenueData)
                && (string) $task->venue_id !== (string) $previousPrivateVenue->getKey()
            ) {
                $previousPrivateVenue->delete();
            }

            if (is_array($privateVenueData) && ! in_array('venue_id', $changedAttributes, true)) {
                $changedAttributes[] = 'venue_id';
            }

            if ($syncAssignee) {
                $this->syncAssignee($task, $assigneeId, $invite);
            } elseif ($changedAttributes !== [] && $task->attendance !== null) {
                $task->notifyAssigneeUpdated($changedAttributes);
            }

            return $task->fresh(['attendance.user', 'venue']) ?? $task;
        });
    }

    private function syncAssignee(Task $task, ?string $assigneeId, bool $invite = true): void
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            return;
        }

        $currentId = $task->attendance?->user_id;

        if ($assigneeId === null || $assigneeId === '') {
            if ($currentId !== null) {
                app(ClearTaskAssigneeAction::class)($task, $actor);
            }

            return;
        }

        if ((string) $currentId === (string) $assigneeId) {
            return;
        }

        $assignee = User::query()->find($assigneeId);

        if (! $assignee instanceof User) {
            throw ValidationException::withMessages([
                'assignee_id' => __('Choose a current member of this team.'),
            ]);
        }

        app(AssignTaskAction::class)($task, $assignee, $actor, invite: $invite);
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
