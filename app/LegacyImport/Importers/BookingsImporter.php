<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use App\LegacyImport\Mapping\ActivityMapping;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BookingsImporter implements Importer
{
    public function key(): string
    {
        return 'bookings';
    }

    public function label(): string
    {
        return 'Bookings, attendance, and related links';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;
        $team = $context->dryRun ? null : $context->team();
        $importedBookingIds = [];

        foreach ($context->legacy()->table('bookings')->orderBy('start_at')->get() as $legacyBooking) {
            $mapping = ActivityMapping::for(
                $legacyBooking->activity,
                $legacyBooking->group_name,
                $legacyBooking->location,
            );

            if ($mapping === null) {
                $result->skipped++;
                $result->addMessage("Skipped booking {$legacyBooking->id}: unmapped activity [{$legacyBooking->activity}].");

                continue;
            }

            if (($mapping['mode'] ?? null) === ActivityMapping::DROP) {
                $result->skipped++;

                continue;
            }

            $result->processed++;
            $importedBookingIds[] = $legacyBooking->id;

            if ($context->dryRun) {
                continue;
            }

            $activityId = null;
            $audienceId = null;
            $activityLabel = $mapping['activity_label'] ?? null;
            $requiresLead = (bool) ($mapping['requires_lead_instructor'] ?? true);
            $leadId = $requiresLead ? $legacyBooking->lead_instructor_id : null;

            if (
                filled($leadId)
                && ! User::query()->whereKey($leadId)->exists()
            ) {
                $leadId = null;
            }

            if (($mapping['mode'] ?? null) === 'catalog') {
                $activity = $context->activity($mapping['activity']);
                $audience = $context->audience($mapping['audience']);
                $activityId = $activity->getKey();
                $audienceId = $audience->getKey();
            }

            $venueId = $this->resolveVenueId($context, $mapping, $legacyBooking->id);

            DB::table('bookings')->updateOrInsert(
                ['id' => $legacyBooking->id],
                [
                    'team_id' => $team->getKey(),
                    'activity_id' => $activityId,
                    'audience_id' => $audienceId,
                    'activity_label' => $activityLabel,
                    'venue_id' => $venueId,
                    'start_at' => $legacyBooking->start_at,
                    'end_at' => $legacyBooking->end_at,
                    'timezone' => $legacyBooking->timezone ?: 'Europe/London',
                    'status' => $legacyBooking->status,
                    'group_name' => $legacyBooking->group_name ?: null,
                    'notes' => $legacyBooking->notes,
                    'lead_instructor_id' => $leadId,
                    'lead_instructor_notes' => $legacyBooking->lead_instructor_notes,
                    'requires_lead_instructor' => $requiresLead,
                    'sequence' => $legacyBooking->sequence ?? 0,
                    'created_at' => $legacyBooking->created_at,
                    'updated_at' => $legacyBooking->updated_at,
                ],
            );

            if (($mapping['private_venue'] ?? false) === true) {
                $this->attachPrivateVenue(
                    $context,
                    $legacyBooking->id,
                    $mapping['private_venue_name'] ?? $legacyBooking->location,
                );
            }
        }

        if (! $context->dryRun) {
            $this->importAttendance($context, $importedBookingIds);
            $this->importRelated($context, $importedBookingIds);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function resolveVenueId(ImportContext $context, array $mapping, string $bookingId): string
    {
        if (($mapping['private_venue'] ?? false) === true) {
            return $context->venue('Fox Coverts Campsite')->getKey();
        }

        $name = $mapping['venue'] ?? 'Fox Coverts Campsite';

        return $context->venue($name)->getKey();
    }

    private function attachPrivateVenue(ImportContext $context, string $bookingId, string $name): void
    {
        $existing = Venue::withoutGlobalScopes()
            ->where('booking_id', $bookingId)
            ->first();

        $venueId = $existing?->getKey() ?? (string) Str::ulid();
        $now = now();

        DB::table('venues')->updateOrInsert(
            ['id' => $venueId],
            [
                'name' => $name,
                'booking_id' => $bookingId,
                'country' => 'GB',
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        DB::table('bookings')->where('id', $bookingId)->update([
            'venue_id' => $venueId,
        ]);
    }

    /**
     * @param  list<string>  $bookingIds
     */
    private function importAttendance(ImportContext $context, array $bookingIds): void
    {
        if ($bookingIds === []) {
            return;
        }

        $context->legacy()
            ->table('booking_user')
            ->whereIn('booking_id', $bookingIds)
            ->orderBy('booking_id')
            ->chunk(200, function ($rows): void {
                $userIds = collect($rows)->pluck('user_id')->unique()->filter()->values();
                $existingUserIds = User::query()
                    ->whereKey($userIds)
                    ->pluck('id')
                    ->all();

                foreach ($rows as $row) {
                    if (! in_array($row->user_id, $existingUserIds, true)) {
                        continue;
                    }

                    DB::table('booking_user')->updateOrInsert(
                        [
                            'booking_id' => $row->booking_id,
                            'user_id' => $row->user_id,
                        ],
                        [
                            'status' => $row->status,
                            'comment' => $row->comment,
                            'token' => $row->token,
                            'calendar_imip_at' => null,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ],
                    );
                }
            });
    }

    /**
     * @param  list<string>  $bookingIds
     */
    private function importRelated(ImportContext $context, array $bookingIds): void
    {
        if ($bookingIds === []) {
            return;
        }

        $links = $context->legacy()
            ->table('bookables')
            ->where('bookable_type', 'App\\Models\\Booking')
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('bookable_id', $bookingIds)
            ->get();

        foreach ($links as $link) {
            $a = $link->booking_id;
            $b = $link->bookable_id;

            if ($a === $b) {
                continue;
            }

            foreach ([[$a, $b], [$b, $a]] as [$left, $right]) {
                DB::table('booking_related')->updateOrInsert(
                    [
                        'booking_id' => $left,
                        'related_id' => $right,
                    ],
                    [],
                );
            }
        }
    }
}
