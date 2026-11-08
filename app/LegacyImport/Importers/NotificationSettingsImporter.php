<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\ImportContext;
use App\LegacyImport\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotificationSettingsImporter implements Importer
{
    public function key(): string
    {
        return 'notifications';
    }

    public function label(): string
    {
        return 'Notification settings';
    }

    public function run(ImportContext $context): ImportResult
    {
        $result = new ImportResult;

        foreach ($context->legacy()->table('notification_settings')->get() as $row) {
            $result->processed++;

            if ($context->dryRun) {
                continue;
            }

            $preferences = [];

            if ($row->invite_mail !== null) {
                $preferences['bookings.mail'] = (bool) $row->invite_mail;
            }

            if ($row->change_mail !== null || $row->confirm_mail !== null || $row->cancel_mail !== null) {
                $preferences['bookings.updates'] = (bool) ($row->change_mail || $row->confirm_mail || $row->cancel_mail);
            }

            if ($row->comment_mail !== null) {
                $preferences['bookings.comments'] = $row->comment_mail !== 'none';
            }

            if ($preferences === []) {
                $result->skipped++;

                continue;
            }

            $subjectType = $row->notifiable_type === 'App\\Models\\Booking'
                ? 'App\\Models\\Booking'
                : null;
            $subjectId = $subjectType ? $row->notifiable_id : null;

            $existing = DB::table('notification_settings')
                ->where('user_id', $row->user_id)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->first();

            DB::table('notification_settings')->updateOrInsert(
                [
                    'user_id' => $row->user_id,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                ],
                [
                    'id' => $existing->id ?? (string) Str::ulid(),
                    'preferences' => json_encode($preferences),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ],
            );
        }

        return $result;
    }
}
