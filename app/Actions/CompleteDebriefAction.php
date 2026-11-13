<?php

namespace App\Actions;

use App\Enums\TeamFeature;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\User;
use App\Support\ManagerAttention;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteDebriefAction
{
    /**
     * @param  array{
     *     hours?: float|string|null,
     *     participant_count?: int|string|null,
     *     weather?: string|null,
     *     notes?: string|null,
     *     equipment_set_ids?: list<string>,
     *     marks: array<string, string>
     * }  $data
     */
    public function __invoke(Booking $booking, User $actor, array $data): BookingDebrief
    {
        $booking->loadMissing('team', 'debrief');

        if ($booking->team === null || ! $booking->team->featureEnabled(TeamFeature::Debrief)) {
            throw ValidationException::withMessages([
                'debrief' => __('Debrief is not enabled for this team.'),
            ]);
        }

        if ($booking->isCancelled()) {
            throw ValidationException::withMessages([
                'debrief' => __('A cancelled booking cannot be debriefed.'),
            ]);
        }

        if (! $booking->isOnOrAfterSessionDay()) {
            throw ValidationException::withMessages([
                'debrief' => __('Debrief is available from the session day onwards.'),
            ]);
        }

        if (! $booking->isLead($actor) && ! $actor->manages($booking->team)) {
            throw ValidationException::withMessages([
                'debrief' => __('Only the lead instructor or a team manager can complete the debrief.'),
            ]);
        }

        if ($booking->debrief instanceof BookingDebrief) {
            throw ValidationException::withMessages([
                'debrief' => __('This booking has already been debriefed.'),
            ]);
        }

        $marks = $data['marks'] ?? [];

        if ($marks === []) {
            throw ValidationException::withMessages([
                'marks' => __('Confirm attendance for everyone on the debrief.'),
            ]);
        }

        foreach ($marks as $mark) {
            if (! in_array($mark, [ConfirmAttendanceAction::MARK_CONFIRM, ConfirmAttendanceAction::MARK_DECLINE], true)) {
                throw ValidationException::withMessages([
                    'marks' => __('Choose attended or did not attend for each person.'),
                ]);
            }
        }

        return DB::transaction(function () use ($booking, $actor, $data, $marks): BookingDebrief {
            app(ConfirmAttendanceAction::class)($booking, $actor, $marks);

            $debrief = BookingDebrief::query()->create([
                'booking_id' => $booking->getKey(),
                'hours' => $this->nullableDecimal($data['hours'] ?? null),
                'participant_count' => $this->nullableInt($data['participant_count'] ?? null),
                'weather' => filled($data['weather'] ?? null) ? trim((string) $data['weather']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'completed_at' => now(),
                'completed_by_id' => $actor->getKey(),
            ]);

            $setIds = collect($data['equipment_set_ids'] ?? [])
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values()
                ->all();

            if ($setIds !== []) {
                $debrief->equipmentSets()->sync($setIds);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($booking)
                ->withProperties([
                    'debrief_id' => $debrief->getKey(),
                    'via' => 'debrief',
                ])
                ->event('debrief_completed')
                ->log(__('Completed the debrief.'));

            ManagerAttention::flush($booking->team_id);

            return $debrief->fresh(['equipmentSets', 'completedBy']) ?? $debrief;
        });
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
