<?php

namespace App\Providers\Filament;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline\ActivityTimelineItem;
use Spatie\Activitylog\Models\Activity as ActivityLog;

class TimelineProvider extends ServiceProvider
{
    public function boot(): void
    {
        Timeline::configureUsing(function (Timeline $timeline): Timeline {
            return $timeline
                ->itemIcons([
                    'created' => Heroicon::OutlinedPlusCircle,
                    'updated' => Heroicon::OutlinedPencilSquare,
                    'deleted' => Heroicon::OutlinedTrash,
                ])
                ->itemIconColors([
                    'created' => Palette::neutral(),
                    'deleted' => Palette::destructive(),
                ])
                ->itemIcon('attendees_invited', Heroicon::OutlinedEnvelope, Booking::class)
                ->itemIconColor('attendees_invited', Palette::neutral(), Booking::class)
                ->itemIcon('attendee_removed', Heroicon::OutlinedUserMinus, Booking::class)
                ->itemIconColor('attendee_removed', Palette::destructive(), Booking::class)
                ->itemIcon('attendance_updated', function (ActivityLog $activity): Heroicon {
                    return static::attendanceStatus($activity)?->getIcon() ?? Heroicon::OutlinedUser;
                }, Booking::class)
                ->itemIconColor('attendance_updated', function (ActivityLog $activity): array {
                    return static::attendanceStatus($activity)?->getColor() ?? Palette::neutral();
                }, Booking::class)
                ->itemIconColor('updated', function (ActivityLog $activity): array {
                    return static::bookingStatus($activity)?->getColor() ?? Palette::neutral();
                }, Booking::class)
                ->attributeLabels([
                    'activity_id' => __('activity'),
                    'venue_id' => __('venue'),
                    'lead_instructor_id' => __('lead instructor'),
                    'team_id' => __('team'),
                ], Booking::class)
                ->attributeValue('activity_id', fn (mixed $value): ?string => filled($value) ? Activity::query()->find($value)?->name : null, Booking::class)
                ->attributeValue('venue_id', fn (mixed $value): ?string => filled($value) ? Venue::query()->find($value)?->name : null, Booking::class)
                ->attributeValue('lead_instructor_id', fn (mixed $value, ActivityLog $activity, Timeline $timeline): ?string => static::linkedUser($timeline, $activity, $value, escape: false), Booking::class)
                ->attributeValue('team_id', fn (mixed $value): ?string => filled($value) ? Team::query()->find($value)?->name : null, Booking::class)
                ->attributeValue('timezone', function (mixed $value): ?string {
                    if ($value instanceof \DateTimeZone) {
                        return $value->getName();
                    }

                    return is_string($value) ? $value : null;
                }, Booking::class)
                ->eventDescription('attendees_invited', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, static::invitedUpdate($timeline, $activity), escape: false), Booking::class)
                ->eventDescription('attendee_removed', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, static::removedUpdate($timeline, $activity), escape: false), Booking::class)
                ->eventDescription('attendance_updated', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, static::attendanceUpdate($timeline, $activity), escape: false), Booking::class)
                ->eventDescription('updated', function (ActivityLog $activity, ?string $causerName, ?string $changesSummary, Timeline $timeline): ?HtmlString {
                    if ($activity->description !== $activity->event) {
                        return static::withActor($timeline, $activity, $causerName, 'noted '.$activity->description.'.', $changesSummary);
                    }

                    $lines = static::changeLines($timeline, $activity);

                    if ($lines->count() < 2) {
                        return null;
                    }

                    $list = '<ul>'.$lines->map(fn (string $line): string => '<li>'.trim($line).'</li>')->implode('').'</ul>';

                    return new HtmlString(static::withActor($timeline, $activity, $causerName, 'made some changes:').$list);
                }, Booking::class);
        });
    }

    protected static function withActor(Timeline $timeline, ActivityLog $activity, ?string $causerName, string $update, ?string $changesSummary = null, bool $escape = true): HtmlString
    {
        if ($escape) {
            $update = e(lcfirst($update));
        }

        if (filled($changesSummary)) {
            $update .= ' '.$changesSummary;
        }

        if (blank($causerName)) {
            return str($update)->inlineMarkdown()->toHtmlString();
        }

        $causerUrl = $timeline->getCauserUrl($activity, $activity->causer);

        if (filled($causerUrl)) {
            $causerName = '['.$causerName.']('.$causerUrl.')';
        }

        return str(__('**:name** :update', [
            'name' => $causerName,
            'update' => $update,
        ]))->inlineMarkdown()->toHtmlString();
    }

    /**
     * @return Collection<int, string>
     */
    protected static function changeLines(Timeline $timeline, ActivityLog $activity): Collection
    {
        $item = new ActivityTimelineItem($activity, $timeline);
        $changes = $activity->attribute_changes;
        $attributes = collect($changes?->get('attributes', []) ?? []);
        $oldAttributes = collect($changes?->get('old', []) ?? []);
        $subject = $activity->relationLoaded('subject') ? $activity->subject : null;
        $ignored = array_filter([
            $subject?->getCreatedAtColumn(),
            $subject?->getUpdatedAtColumn(),
            $subject !== null && method_exists($subject, 'getDeletedAtColumn') ? $subject->getDeletedAtColumn() : null,
        ]);

        return $attributes
            ->reject(fn (mixed $value, string $key): bool => in_array($key, $ignored, true))
            ->filter(fn (mixed $value, string $key): bool => $item->parseAttributeValue($oldAttributes->get($key), $key, 'old') !== $item->parseAttributeValue($value, $key))
            ->map(fn (mixed $value, string $key): string => (string) str(static::changeClause($timeline, $item, $key, $value, $oldAttributes->get($key)))->inlineMarkdown())
            ->values();
    }

    protected static function changeClause(Timeline $timeline, ActivityTimelineItem $item, string $key, mixed $value, mixed $oldValue): string
    {
        $attributeLabel = $item->getAttributeLabel($key);

        if (! $timeline->isChangesSummaryAttributeValuesVisible($key)) {
            return $attributeLabel;
        }

        $attributeValue = $item->parseAttributeValue($value, $key);
        $oldAttributeValue = $item->parseAttributeValue($oldValue, $key, 'old');
        $showOld = $timeline->isChangesSummaryAttributeOldValuesVisible($key);

        if (blank($attributeValue)) {
            if (blank($oldAttributeValue)) {
                return __('filament-activitylog::translations.activity-timeline-item.event-descriptions.changesSummary.attributeFromBlankToBlankWithOld', [
                    'attributeLabel' => $attributeLabel,
                    'oldAttributeValue' => $oldAttributeValue,
                    'newAttributeValue' => $attributeValue,
                ]);
            }

            if ($showOld) {
                return __('filament-activitylog::translations.activity-timeline-item.event-descriptions.changesSummary.attributeToBlankWithOld', [
                    'attributeLabel' => $attributeLabel,
                    'oldAttributeValue' => $oldAttributeValue,
                    'newAttributeValue' => $attributeValue,
                ]);
            }

            return __('filament-activitylog::translations.activity-timeline-item.event-descriptions.changesSummary.attributeToBlank', [
                'attributeLabel' => $attributeLabel,
                'newAttributeValue' => $attributeValue,
            ]);
        }

        if ($showOld) {
            if (blank($oldAttributeValue)) {
                return __('filament-activitylog::translations.activity-timeline-item.event-descriptions.changesSummary.attributeFromBlankWithOld', [
                    'attributeLabel' => $attributeLabel,
                    'oldAttributeValue' => $oldAttributeValue,
                    'newAttributeValue' => $attributeValue,
                ]);
            }

            return __('filament-activitylog::translations.activity-timeline-item.event-descriptions.changesSummary.attributeWithOld', [
                'attributeLabel' => $attributeLabel,
                'oldAttributeValue' => $oldAttributeValue,
                'newAttributeValue' => $attributeValue,
            ]);
        }

        return __('filament-activitylog::translations.activity-timeline-item.event-descriptions.changesSummary.attribute', [
            'attributeLabel' => $attributeLabel,
            'newAttributeValue' => $attributeValue,
        ]);
    }

    protected static function attendanceUpdate(Timeline $timeline, ActivityLog $activity): string
    {
        $attendeeId = data_get($activity->properties, 'attendee_id');
        $name = static::linkedUser($timeline, $activity, $attendeeId);
        $status = static::attendanceStatus($activity);

        if ($status instanceof BookingAttendeeStatus && filled($attendeeId) && (string) $activity->causer_id === (string) $attendeeId) {
            $statusLabel = e(lcfirst($status->getLabel()));

            return $status === BookingAttendeeStatus::Tentative
                ? __('said **:status**.', ['status' => $statusLabel])
                : __('said they **:status**.', ['status' => $statusLabel]);
        }

        if (filled($name) && $status instanceof BookingAttendeeStatus) {
            return __('recorded **:status** for :name.', [
                'status' => e($status->getLabel()),
                'name' => $name,
            ]);
        }

        return e(lcfirst($activity->description));
    }

    protected static function invitedUpdate(Timeline $timeline, ActivityLog $activity): string
    {
        $names = static::linkedUsers($timeline, $activity, data_get($activity->properties, 'user_ids', []));

        if ($names->isEmpty()) {
            return e(lcfirst($activity->description));
        }

        return __('invited :names.', ['names' => static::naturalList($names)]);
    }

    protected static function removedUpdate(Timeline $timeline, ActivityLog $activity): string
    {
        $name = static::linkedUser($timeline, $activity, data_get($activity->properties, 'user_id'));

        if (blank($name)) {
            return e(lcfirst($activity->description));
        }

        return __('removed :name.', ['name' => $name]);
    }

    protected static function linkedUser(Timeline $timeline, ActivityLog $activity, mixed $id, bool $escape = true): ?string
    {
        if (blank($id)) {
            return null;
        }

        $user = User::query()->find($id);

        if (! $user instanceof User) {
            return null;
        }

        $name = $escape ? e($user->name) : $user->name;
        $url = $timeline->getRecordUrl($activity, $user);

        if (blank($url)) {
            return $escape ? '**'.$name.'**' : $name;
        }

        return '['.$name.']('.$url.')';
    }

    /**
     * @param  iterable<mixed>  $ids
     * @return Collection<int, string>
     */
    protected static function linkedUsers(Timeline $timeline, ActivityLog $activity, iterable $ids): Collection
    {
        $ids = collect($ids)->filter()->values();
        $users = User::query()->whereKey($ids)->get()->keyBy(fn (User $user): mixed => $user->getKey());

        return $ids
            ->map(function (mixed $id) use ($activity, $timeline, $users): ?string {
                $user = $users->get($id);

                if (! $user instanceof User) {
                    return null;
                }

                $name = e($user->name);
                $url = $timeline->getRecordUrl($activity, $user);

                return filled($url) ? '['.$name.']('.$url.')' : '**'.$name.'**';
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $labels
     */
    protected static function naturalList(Collection $labels): string
    {
        if ($labels->count() < 2) {
            return (string) $labels->first();
        }

        if ($labels->count() === 2) {
            return $labels->implode(' and ');
        }

        return $labels->slice(0, -1)->implode(', ').', and '.$labels->last();
    }

    protected static function attendanceStatus(ActivityLog $activity): ?BookingAttendeeStatus
    {
        $status = data_get($activity->properties, 'status');

        return is_string($status) ? BookingAttendeeStatus::tryFrom($status) : null;
    }

    protected static function bookingStatus(ActivityLog $activity): ?BookingStatus
    {
        $status = data_get($activity->attribute_changes, 'attributes.status');

        return is_string($status) ? BookingStatus::tryFrom($status) : null;
    }
}
