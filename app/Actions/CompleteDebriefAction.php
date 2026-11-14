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
     *     hours: float|string|int,
     *     participant_count: int|string,
     *     weather: string,
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

        $hours = $this->requiredDecimal($data['hours'] ?? null, 'hours', __('Operating hours are required.'));
        $participantCount = $this->requiredInt(
            $data['participant_count'] ?? null,
            'participant_count',
            __('Participant numbers are required.'),
        );
        $weather = $this->requiredString($data['weather'] ?? null, 'weather', __('Weather is required.'));

        $marks = $data['marks'] ?? [];

        foreach ($marks as $mark) {
            if (! in_array($mark, [
                ConfirmAttendanceAction::MARK_CONFIRM,
                ConfirmAttendanceAction::MARK_DECLINE,
                ConfirmAttendanceAction::MARK_SKIP,
            ], true)) {
                throw ValidationException::withMessages([
                    'marks' => __('Choose attended, did not attend, or leave blank for each person.'),
                ]);
            }
        }

        return DB::transaction(function () use ($booking, $actor, $data, $marks, $hours, $participantCount, $weather): BookingDebrief {
            app(ConfirmAttendanceAction::class)($booking, $actor, $marks);

            $debrief = BookingDebrief::query()->create([
                'booking_id' => $booking->getKey(),
                'hours' => $hours,
                'participant_count' => $participantCount,
                'weather' => $weather,
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

    private function requiredDecimal(mixed $value, string $field, string $message): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function requiredInt(mixed $value, string $field, string $message): int
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        return (int) $value;
    }

    private function requiredString(mixed $value, string $field, string $message): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        return $trimmed;
    }
}
