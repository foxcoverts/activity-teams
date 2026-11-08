<?php

namespace App\LegacyImport\Importers;

use App\Enums\BookingAttendeeStatus;
use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BookingChangesImporter implements Importer
{
    /**
     * Legacy change_fields that belong on bookings (ignore todo-only names).
     *
     * @var list<string>
     */
    private const BOOKING_FIELDS = [
        'status',
        'lead_instructor_id',
        'start_at',
        'end_at',
        'notes',
        'lead_instructor_notes',
        'group_name',
    ];

    public function key(): string
    {
        return 'changes';
    }

    public function label(): string
    {
        return 'Booking timeline (changes → activity log)';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;

        if ($context->dryRun) {
            $result->processed = $context->legacy()
                ->table('changes')
                ->where('changeable_type', 'App\\Models\\Booking')
                ->count();

            return $result;
        }

        $existingBookingIds = DB::table('bookings')->pluck('id')->all();
        $existingBookingIdSet = array_fill_keys($existingBookingIds, true);
        $existingUserIds = array_fill_keys(User::query()->pluck('id')->all(), true);

        $changes = $context->legacy()
            ->table('changes')
            ->where('changeable_type', 'App\\Models\\Booking')
            ->orderBy('created_at')
            ->get();

        $changeIds = $changes->pluck('id')->all();
        $fieldsByChange = $this->groupByChangeId(
            $context->legacy()->table('change_fields')->whereIn('change_id', $changeIds)->get(),
        );
        $commentsByChange = $this->groupByChangeId(
            $context->legacy()->table('change_comments')->whereIn('change_id', $changeIds)->get(),
        );
        $attendeesByChange = $this->groupByChangeId(
            $context->legacy()->table('change_attendees')->whereIn('change_id', $changeIds)->get(),
        );

        foreach ($changes as $change) {
            if (! isset($existingBookingIdSet[$change->changeable_id])) {
                $result->skipped++;

                continue;
            }

            $fields = $fieldsByChange->get($change->id, collect());
            $comments = $commentsByChange->get($change->id, collect());
            $attendees = $attendeesByChange->get($change->id, collect());

            if ($fields->isEmpty() && $comments->isEmpty() && $attendees->isEmpty()) {
                $result->skipped++;

                continue;
            }

            $result->processed++;

            $causerId = isset($existingUserIds[$change->author_id]) ? $change->author_id : null;
            $attributes = $this->bookingAttributes($fields);
            $cancellationReason = null;

            if (
                ($attributes['status'] ?? null) === 'cancelled'
                && $comments->isNotEmpty()
            ) {
                $cancellationReason = (string) $comments->first()->body;
                $comments = collect();
            }

            if ($attributes !== []) {
                $this->upsertActivity(
                    legacyChangeId: $change->id,
                    kind: 'fields',
                    subjectId: $change->changeable_id,
                    causerId: $causerId,
                    event: 'updated',
                    description: $cancellationReason ?? 'updated',
                    attributeChanges: ['attributes' => $attributes, 'old' => []],
                    properties: ['legacy_change_id' => $change->id, 'legacy_kind' => 'fields'],
                    createdAt: $change->created_at,
                    updatedAt: $change->updated_at,
                );
            }

            foreach ($attendees as $attendee) {
                $status = BookingAttendeeStatus::tryFrom((string) $attendee->attendee_status);

                if ($status === null || ! isset($existingUserIds[$attendee->attendee_id])) {
                    continue;
                }

                $this->upsertActivity(
                    legacyChangeId: $change->id,
                    kind: 'attendance:'.$attendee->attendee_id,
                    subjectId: $change->changeable_id,
                    causerId: $causerId,
                    event: 'attendance_updated',
                    description: 'Attendance updated',
                    attributeChanges: null,
                    properties: [
                        'legacy_change_id' => $change->id,
                        'legacy_kind' => 'attendance',
                        'attendee_id' => $attendee->attendee_id,
                        'status' => $status->value,
                        'comment' => $attendee->attendee_comment,
                    ],
                    createdAt: $change->created_at,
                    updatedAt: $change->updated_at,
                );
            }

            foreach ($comments as $comment) {
                DB::table('comments')->updateOrInsert(
                    ['id' => $comment->id],
                    [
                        'commentable_type' => 'App\\Models\\Booking',
                        'commentable_id' => $change->changeable_id,
                        'author_id' => $causerId,
                        'body' => $comment->body,
                        'created_at' => $change->created_at,
                        'updated_at' => $change->updated_at,
                        'deleted_at' => null,
                    ],
                );
            }
        }

        $this->synthesizeCreatedEvents($existingBookingIds, $result);

        return $result;
    }

    /**
     * @param  list<string>  $bookingIds
     */
    private function synthesizeCreatedEvents(array $bookingIds, ImportResult $result): void
    {
        if ($bookingIds === []) {
            return;
        }

        $alreadyCreated = DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\Booking')
            ->whereIn('subject_id', $bookingIds)
            ->where('event', 'created')
            ->where('properties->legacy_kind', 'created')
            ->pluck('subject_id')
            ->all();
        $alreadyCreatedSet = array_fill_keys($alreadyCreated, true);

        $bookings = DB::table('bookings')
            ->whereIn('id', $bookingIds)
            ->get(['id', 'created_at', 'updated_at']);

        foreach ($bookings as $booking) {
            if (isset($alreadyCreatedSet[$booking->id])) {
                continue;
            }

            $result->processed++;

            DB::table('activity_log')->insert([
                'log_name' => 'default',
                'description' => 'created',
                'subject_type' => 'App\\Models\\Booking',
                'subject_id' => $booking->id,
                'event' => 'created',
                'causer_type' => null,
                'causer_id' => null,
                'attribute_changes' => null,
                'properties' => json_encode([
                    'legacy_kind' => 'created',
                ]),
                'created_at' => $booking->created_at,
                'updated_at' => $booking->created_at ?? $booking->updated_at,
            ]);
        }
    }

    /**
     * @param  Collection<int, object>  $fields
     * @return array<string, mixed>
     */
    private function bookingAttributes(Collection $fields): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $name = (string) $field->name;

            if (! in_array($name, self::BOOKING_FIELDS, true)) {
                continue;
            }

            $value = $field->value;

            if ($value === '' || $value === 'null') {
                $value = null;
            }

            $attributes[$name] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>|null  $attributeChanges
     * @param  array<string, mixed>  $properties
     */
    private function upsertActivity(
        string $legacyChangeId,
        string $kind,
        string $subjectId,
        ?string $causerId,
        string $event,
        string $description,
        ?array $attributeChanges,
        array $properties,
        mixed $createdAt,
        mixed $updatedAt,
    ): void {
        $existingId = DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\Booking')
            ->where('subject_id', $subjectId)
            ->where('properties->legacy_change_id', $legacyChangeId)
            ->where('properties->legacy_kind', $properties['legacy_kind'] ?? $kind)
            ->when(
                str_starts_with($kind, 'attendance:'),
                fn ($query) => $query->where('properties->attendee_id', substr($kind, strlen('attendance:'))),
            )
            ->value('id');

        $payload = [
            'log_name' => 'default',
            'description' => $description,
            'subject_type' => 'App\\Models\\Booking',
            'subject_id' => $subjectId,
            'event' => $event,
            'causer_type' => $causerId !== null ? 'App\\Models\\User' : null,
            'causer_id' => $causerId,
            'attribute_changes' => $attributeChanges === null ? null : json_encode($attributeChanges),
            'properties' => json_encode($properties),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        if ($existingId !== null) {
            DB::table('activity_log')->where('id', $existingId)->update($payload);

            return;
        }

        DB::table('activity_log')->insert($payload);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<string, Collection<int, object>>
     */
    private function groupByChangeId(Collection $rows): Collection
    {
        return $rows->groupBy(fn (object $row): string => (string) $row->change_id);
    }
}
