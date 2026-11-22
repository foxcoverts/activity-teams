<?php

namespace App\Providers\Filament;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\Palette;
use App\Support\TimelineView;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
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
                ->itemDateTimeFormat('j M Y, H:i')
                ->attributeCast('datetime', fn (mixed $value): ?string => static::formatDateTimeValue($value))
                ->attributeCast('immutable_datetime', fn (mixed $value): ?string => static::formatDateTimeValue($value))
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
                ->itemIcon('attendance_confirmed', function (ActivityLog $activity): Heroicon {
                    return static::attendanceStatus($activity)?->getIcon() ?? Heroicon::OutlinedUser;
                }, Booking::class)
                ->itemIconColor('attendance_confirmed', function (ActivityLog $activity): array {
                    return static::attendanceStatus($activity)?->getColor() ?? Palette::neutral();
                }, Booking::class)
                ->itemIcon('lead_stepped_down', function (ActivityLog $activity): Heroicon {
                    return static::attendanceStatus($activity)?->getIcon() ?? Heroicon::OutlinedUserMinus;
                }, Booking::class)
                ->itemIconColor('lead_stepped_down', function (ActivityLog $activity): array {
                    return static::attendanceStatus($activity)?->getColor() ?? Palette::uncertain();
                }, Booking::class)
                ->itemIcon('debrief_completed', Heroicon::OutlinedDocumentCheck, Booking::class)
                ->itemIconColor('debrief_completed', Palette::positive(), Booking::class)
                ->itemIconColor('updated', function (ActivityLog $activity): array {
                    return static::bookingStatus($activity)?->getColor() ?? Palette::neutral();
                }, Booking::class)
                ->attributeLabels([
                    'activity_id' => __('activity'),
                    'audience_id' => __('audience'),
                    'venue_id' => __('venue'),
                    'start_at' => __('start'),
                    'end_at' => __('end'),
                    'lead_needed_at' => __('lead needed'),
                    'notes' => __('team notes'),
                    'lead_instructor_notes' => __('lead instructor notes'),
                    'lead_instructor_id' => __('lead instructor'),
                    'requires_lead_instructor' => __('require lead instructor'),
                    'team_id' => __('team'),
                    'activity_label' => __('activity name'),
                ], Booking::class)
                ->attributeLabels([
                    'summary' => __('title'),
                    'description' => __('notes'),
                    'venue_id' => __('venue'),
                    'team_id' => __('team'),
                    'due_at' => __('due'),
                    'started_at' => __('started'),
                    'completed_at' => __('completed'),
                ], Task::class)
                ->attributeValue('activity_id', fn (mixed $value): ?string => filled($value) ? Activity::query()->find($value)?->name : null, Booking::class)
                ->attributeValue('audience_id', fn (mixed $value): ?string => filled($value) ? Audience::query()->find($value)?->name : null, Booking::class)
                ->attributeValue('venue_id', fn (mixed $value): ?string => filled($value) ? Venue::withPrivate()->find($value)?->name : null, Booking::class)
                ->attributeValue('lead_instructor_id', fn (mixed $value, ActivityLog $activity, Timeline $timeline): ?string => static::linkedUser($timeline, $activity, $value, escape: false), Booking::class)
                ->attributeValue('team_id', fn (mixed $value): ?string => filled($value) ? Team::query()->find($value)?->name : null, Booking::class)
                ->attributeValue('timezone', function (mixed $value): ?string {
                    if ($value instanceof \DateTimeZone) {
                        return $value->getName();
                    }

                    return is_string($value) ? $value : null;
                }, Booking::class)
                ->attributeValue('venue_id', fn (mixed $value): ?string => filled($value) ? Venue::withPrivate()->find($value)?->name : null, Task::class)
                ->attributeValue('team_id', fn (mixed $value): ?string => filled($value) ? Team::query()->find($value)?->name : null, Task::class)
                ->attributeValue('status', fn (mixed $value): ?string => static::taskStatusLabel($value), Task::class)
                ->attributeValue('priority', fn (mixed $value): ?string => static::taskPriorityLabel($value), Task::class)
                // Own created/deleted/restored so we return HtmlString and avoid the package
                // path that e()s the causer name and then e()s again before inlineMarkdown.
                ->eventDescription('created', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, 'created the booking.'), Booking::class)
                ->eventDescription('deleted', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, 'deleted the booking.'), Booking::class)
                ->eventDescription('restored', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, 'restored the booking.'), Booking::class)
                ->eventDescription('attendees_invited', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, static::invitedUpdate($timeline, $activity), escape: false), Booking::class)
                ->eventDescription('attendee_removed', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, static::removedUpdate($timeline, $activity), escape: false), Booking::class)
                ->eventDescription('attendance_updated', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::bookingAttendanceDescription($timeline, $activity, $causerName), Booking::class)
                ->eventDescription('attendance_confirmed', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::bookingAttendanceDescription($timeline, $activity, $causerName), Booking::class)
                ->eventDescription('lead_stepped_down', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::leadSteppedDownDescription($timeline, $activity, $causerName), Booking::class)
                ->eventDescription('debrief_completed', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, 'completed the debrief.'), Booking::class)
                ->eventDescription('updated', function (ActivityLog $activity, ?string $causerName, ?string $changesSummary, Timeline $timeline): ?HtmlString {
                    return static::updatedDescription($timeline, $activity, $causerName, $changesSummary);
                }, Booking::class)
                ->itemIconColor('updated', function (ActivityLog $activity): array {
                    return static::taskStatus($activity)?->getColor() ?? Palette::neutral();
                }, Task::class)
                ->itemIcon('assignee_assigned', Heroicon::OutlinedUserPlus, Task::class)
                ->itemIconColor('assignee_assigned', Palette::neutral(), Task::class)
                ->itemIcon('assignee_cleared', Heroicon::OutlinedUserMinus, Task::class)
                ->itemIconColor('assignee_cleared', Palette::destructive(), Task::class)
                ->itemIcon('assignee_responded', function (ActivityLog $activity): Heroicon {
                    return static::taskAssigneeStatus($activity)?->getIcon() ?? Heroicon::OutlinedUser;
                }, Task::class)
                ->itemIconColor('assignee_responded', function (ActivityLog $activity): array {
                    return static::taskAssigneeStatus($activity)?->getColor() ?? Palette::neutral();
                }, Task::class)
                ->eventDescription('created', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, 'created the task.'), Task::class)
                ->eventDescription('deleted', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, 'deleted the task.'), Task::class)
                ->eventDescription('assignee_assigned', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::taskAssigneeAssignedDescription($timeline, $activity, $causerName), Task::class)
                ->eventDescription('assignee_cleared', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::withActor($timeline, $activity, $causerName, static::taskAssigneeClearedUpdate($timeline, $activity), escape: false), Task::class)
                ->eventDescription('assignee_responded', fn (ActivityLog $activity, ?string $causerName, Timeline $timeline): HtmlString => static::taskAssigneeRespondedDescription($timeline, $activity, $causerName), Task::class)
                ->eventDescription('updated', function (ActivityLog $activity, ?string $causerName, ?string $changesSummary, Timeline $timeline): ?HtmlString {
                    return static::updatedDescription($timeline, $activity, $causerName, $changesSummary);
                }, Task::class);
        });
    }

    protected static function updatedDescription(
        Timeline $timeline,
        ActivityLog $activity,
        ?string $causerName,
        ?string $changesSummary,
    ): ?HtmlString {
        if ($activity->description !== $activity->event) {
            return static::withActor($timeline, $activity, $causerName, 'noted '.$activity->description.'.', $changesSummary);
        }

        $clauses = static::changeClauses($timeline, $activity);

        if ($clauses->isEmpty()) {
            // Truthy empty HtmlString: null falls through to the package default and leaks filtered fields.
            return new HtmlString('');
        }

        if ($clauses->count() === 1) {
            return static::withActor($timeline, $activity, $causerName, $clauses->first().'.', escape: false);
        }

        $list = '<ul>'.$clauses
            ->map(fn (string $clause): string => '<li>'.trim((string) str($clause)->inlineMarkdown()).'</li>')
            ->implode('').'</ul>';

        return new HtmlString((string) static::withActor($timeline, $activity, $causerName, 'made some changes:').$list);
    }

    /**
     * Build a timeline sentence. The activitylog package passes an already-escaped
     * $causerName; decode it and let inlineMarkdown perform a single HTML escape.
     * Do not e() text that will be fed to markdown — that double-encodes apostrophes.
     */
    protected static function withActor(Timeline $timeline, ActivityLog $activity, ?string $causerName, string $update, ?string $changesSummary = null, bool $escape = true): HtmlString
    {
        if ($escape) {
            $update = lcfirst($update);
        }

        if (filled($changesSummary)) {
            $update .= ' '.$changesSummary;
        }

        $causerName = static::rawCauserName($causerName);

        if (blank($causerName)) {
            return str(str($update)->ucfirst())->inlineMarkdown()->toHtmlString();
        }

        $causerUrl = $timeline->getCauserUrl($activity, $activity->causer);

        if (filled($causerUrl)) {
            $causerName = '['.static::markdownLinkText($causerName).']('.$causerUrl.')';
        }

        return str(__('**:name** :update', [
            'name' => $causerName,
            'update' => $update,
        ]))->inlineMarkdown()->toHtmlString();
    }

    /**
     * The package HTML-escapes the causer before invoking eventDescription callbacks.
     */
    protected static function rawCauserName(?string $causerName): ?string
    {
        if ($causerName === null || $causerName === '') {
            return $causerName;
        }

        return html_entity_decode($causerName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected static function markdownLinkText(string $text): string
    {
        return str_replace(['[', ']', '\\'], ['\\[', '\\]', '\\\\'], $text);
    }

    /**
     * Markdown verb phrases for each changed attribute, e.g. "set the **audience** to **Scouts**".
     *
     * @return Collection<int, string>
     */
    protected static function changeClauses(Timeline $timeline, ActivityLog $activity): Collection
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

        $hiddenForMember = [];

        if (TimelineView::audience()->isMember()) {
            $hiddenForMember[] = 'lead_needed_at';

            if (! TimelineView::viewerIsLead()) {
                $hiddenForMember[] = 'lead_instructor_notes';
            }
        }

        return $attributes
            ->reject(fn (mixed $value, string $key): bool => in_array($key, $ignored, true)
                || in_array($key, $hiddenForMember, true))
            ->filter(fn (mixed $value, string $key): bool => $item->parseAttributeValue($oldAttributes->get($key), $key, 'old') !== $item->parseAttributeValue($value, $key))
            ->map(fn (mixed $value, string $key): string => static::changeClause($timeline, $item, $key, $value, $oldAttributes->get($key)))
            ->values();
    }

    protected static function changeClause(Timeline $timeline, ActivityTimelineItem $item, string $key, mixed $value, mixed $oldValue): string
    {
        $attributeLabel = $item->getAttributeLabel($key);

        if (static::isLongTextAttribute($key)) {
            return static::longTextChangeClause($attributeLabel, $value, $oldValue);
        }

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

    protected static function isLongTextAttribute(string $key): bool
    {
        return in_array($key, ['notes', 'lead_instructor_notes', 'description'], true);
    }

    protected static function longTextChangeClause(string $attributeLabel, mixed $value, mixed $oldValue): string
    {
        $newText = static::rawTextValue($value);
        $oldText = static::rawTextValue($oldValue);

        if (blank($newText)) {
            return __('cleared the **:attributeLabel**', ['attributeLabel' => $attributeLabel]);
        }

        if (blank($oldText)) {
            return __('set the **:attributeLabel**', ['attributeLabel' => $attributeLabel]);
        }

        return __('updated the **:attributeLabel**', ['attributeLabel' => $attributeLabel]);
    }

    protected static function rawTextValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected static function formatDateTimeValue(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->timezone(config('app.timezone'))->format('j M Y, H:i');
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)
                ->timezone(config('app.timezone'))
                ->format('j M Y, H:i');
        }

        return null;
    }

    protected static function bookingAttendanceDescription(
        Timeline $timeline,
        ActivityLog $activity,
        ?string $causerName,
    ): HtmlString {
        [$actorName, $update] = static::bookingAttendanceParts($timeline, $activity, $causerName);

        return static::withActor($timeline, $activity, $actorName, $update, escape: false);
    }

    protected static function leadSteppedDownDescription(
        Timeline $timeline,
        ActivityLog $activity,
        ?string $causerName,
    ): HtmlString {
        [$actorName, $attendance] = static::bookingAttendanceParts($timeline, $activity, $causerName);
        $attendance = rtrim($attendance, '.');
        $becameTentative = data_get($activity->properties, 'booking_status') === BookingStatus::Tentative->value;

        $update = $becameTentative
            ? __(':attendance and stepped down as lead instructor, leaving the booking tentative.', [
                'attendance' => $attendance,
            ])
            : __(':attendance and stepped down as lead instructor.', [
                'attendance' => $attendance,
            ]);

        return static::withActor($timeline, $activity, $actorName, $update, escape: false);
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    protected static function bookingAttendanceParts(
        Timeline $timeline,
        ActivityLog $activity,
        ?string $causerName,
    ): array {
        $attendeeId = data_get($activity->properties, 'attendee_id');
        $name = static::linkedUser($timeline, $activity, $attendeeId);
        $status = static::attendanceStatus($activity);
        $isSelf = $status instanceof BookingAttendeeStatus
            && filled($attendeeId)
            && (string) $activity->causer_id === (string) $attendeeId;
        $viewer = TimelineView::viewer();
        $viewerIsAttendee = $viewer instanceof User
            && filled($attendeeId)
            && (string) $viewer->getKey() === (string) $attendeeId;

        if ($isSelf && $status instanceof BookingAttendeeStatus) {
            return [$causerName, static::selfBookingAttendancePhrase($status)];
        }

        if (TimelineView::audience()->isMember() && $status instanceof BookingAttendeeStatus && filled($attendeeId)) {
            if ($viewerIsAttendee) {
                return [$causerName, static::recordedBookingAttendanceForYouPhrase($status)];
            }

            $attendee = User::query()->find($attendeeId);

            return [
                $attendee instanceof User ? $attendee->name : $causerName,
                static::selfBookingAttendancePhrase($status),
            ];
        }

        if (filled($name) && $status instanceof BookingAttendeeStatus) {
            return [$causerName, __('recorded **:status** for :name.', [
                'status' => $status->getLabel(),
                'name' => $name,
            ])];
        }

        return [$causerName, lcfirst((string) $activity->description)];
    }

    protected static function selfBookingAttendancePhrase(BookingAttendeeStatus $status): string
    {
        $statusLabel = lcfirst($status->getLabel());

        return $status === BookingAttendeeStatus::Tentative
            ? __('said **:status**.', ['status' => $statusLabel])
            : __('said they **:status**.', ['status' => $statusLabel]);
    }

    protected static function recordedBookingAttendanceForYouPhrase(BookingAttendeeStatus $status): string
    {
        $statusLabel = lcfirst($status->getLabel());

        return $status === BookingAttendeeStatus::Tentative
            ? __('recorded that you said **:status**.', ['status' => $statusLabel])
            : __('recorded that you **:status**.', ['status' => $statusLabel]);
    }

    protected static function invitedUpdate(Timeline $timeline, ActivityLog $activity): string
    {
        $names = static::linkedUsers(
            $timeline,
            $activity,
            data_get($activity->properties, 'user_ids', []),
            themselfId: $activity->causer_id,
        );

        if ($names->isEmpty()) {
            return lcfirst((string) $activity->description);
        }

        return __('invited :names.', ['names' => static::naturalList($names)]);
    }

    protected static function removedUpdate(Timeline $timeline, ActivityLog $activity): string
    {
        $name = static::linkedUser($timeline, $activity, data_get($activity->properties, 'user_id'));

        if (blank($name)) {
            return lcfirst((string) $activity->description);
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

        // Raw name: inlineMarkdown escapes HTML once. Pre-escaping here double-encodes apostrophes.
        $name = $user->name;
        $url = $timeline->getRecordUrl($activity, $user);

        if (blank($url)) {
            return $escape ? '**'.$name.'**' : $name;
        }

        return '['.static::markdownLinkText($name).']('.$url.')';
    }

    /**
     * @param  iterable<mixed>  $ids
     * @return Collection<int, string>
     */
    protected static function linkedUsers(
        Timeline $timeline,
        ActivityLog $activity,
        iterable $ids,
        mixed $themselfId = null,
    ): Collection {
        $ids = collect($ids)->filter()->values();
        $users = User::query()->whereKey($ids)->get()->keyBy(fn (User $user): mixed => $user->getKey());

        return $ids
            ->map(function (mixed $id) use ($activity, $timeline, $users, $themselfId): ?string {
                if ($themselfId !== null && (string) $id === (string) $themselfId) {
                    return __('themself');
                }

                $user = $users->get($id);

                if (! $user instanceof User) {
                    return null;
                }

                $name = $user->name;
                $url = $timeline->getRecordUrl($activity, $user);

                return filled($url)
                    ? '['.static::markdownLinkText($name).']('.$url.')'
                    : '**'.$name.'**';
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

    protected static function taskStatus(ActivityLog $activity): ?TaskStatus
    {
        $status = data_get($activity->attribute_changes, 'attributes.status');

        return is_string($status) ? TaskStatus::tryFrom($status) : null;
    }

    protected static function taskStatusLabel(mixed $value): ?string
    {
        if ($value instanceof TaskStatus) {
            return $value->getLabel();
        }

        return is_string($value) ? TaskStatus::tryFrom($value)?->getLabel() : null;
    }

    protected static function taskPriorityLabel(mixed $value): ?string
    {
        if ($value instanceof TaskPriority) {
            return $value->getLabel();
        }

        if (is_int($value) || (is_string($value) && is_numeric($value))) {
            return TaskPriority::tryFrom((int) $value)?->getLabel();
        }

        return null;
    }

    protected static function taskAssigneeStatus(ActivityLog $activity): ?TaskAttendeeStatus
    {
        $status = data_get($activity->properties, 'status');

        return is_string($status) ? TaskAttendeeStatus::tryFrom($status) : null;
    }

    protected static function taskAssigneeAssignedDescription(
        Timeline $timeline,
        ActivityLog $activity,
        ?string $causerName,
    ): HtmlString {
        $userId = data_get($activity->properties, 'user_id');
        $invited = (bool) data_get($activity->properties, 'invite');
        $isThemself = filled($userId) && (string) $activity->causer_id === (string) $userId;
        $name = $isThemself
            ? __('themself')
            : static::linkedUser($timeline, $activity, $userId);
        $viewer = TimelineView::viewer();
        $viewerIsAssignee = $viewer instanceof User
            && filled($userId)
            && (string) $viewer->getKey() === (string) $userId;

        if (TimelineView::audience()->isMember() && ! $invited) {
            if ($viewerIsAssignee) {
                return static::withActor($timeline, $activity, $causerName, __('assigned you.'));
            }

            if (filled($name)) {
                return static::plainDescription(__(':name was assigned.', ['name' => $name]));
            }

            return static::plainDescription(__('Someone was assigned.'));
        }

        if (! filled($name)) {
            return static::withActor(
                $timeline,
                $activity,
                $causerName,
                $invited ? __('sent an assignment invite.') : __('assigned someone.'),
            );
        }

        return static::withActor(
            $timeline,
            $activity,
            $causerName,
            $invited
                ? __('invited :name.', ['name' => $name])
                : __('assigned :name.', ['name' => $name]),
            escape: false,
        );
    }

    protected static function taskAssigneeClearedUpdate(Timeline $timeline, ActivityLog $activity): string
    {
        $name = static::linkedUser($timeline, $activity, data_get($activity->properties, 'user_id'));

        return filled($name)
            ? __('removed :name as assignee.', ['name' => $name])
            : __('cleared the assignee.');
    }

    protected static function taskAssigneeRespondedDescription(
        Timeline $timeline,
        ActivityLog $activity,
        ?string $causerName,
    ): HtmlString {
        $userId = data_get($activity->properties, 'user_id');
        $name = static::linkedUser($timeline, $activity, $userId);
        $status = static::taskAssigneeStatus($activity);
        $isSelf = $status instanceof TaskAttendeeStatus
            && filled($userId)
            && (string) $activity->causer_id === (string) $userId;
        $viewer = TimelineView::viewer();
        $viewerIsAssignee = $viewer instanceof User
            && filled($userId)
            && (string) $viewer->getKey() === (string) $userId;

        if ($isSelf && $status instanceof TaskAttendeeStatus) {
            return static::withActor($timeline, $activity, $causerName, static::selfTaskResponsePhrase($status));
        }

        if (TimelineView::audience()->isMember() && $status instanceof TaskAttendeeStatus && filled($userId)) {
            if ($viewerIsAssignee) {
                return static::withActor(
                    $timeline,
                    $activity,
                    $causerName,
                    static::recordedTaskResponseForYouPhrase($status),
                );
            }

            $assignee = User::query()->find($userId);

            return static::withActor(
                $timeline,
                $activity,
                $assignee instanceof User ? $assignee->name : $causerName,
                static::selfTaskResponsePhrase($status),
            );
        }

        if ($status instanceof TaskAttendeeStatus && filled($name)) {
            return static::withActor($timeline, $activity, $causerName, __('recorded **:status** for :name.', [
                'status' => $status->getLabel(),
                'name' => $name,
            ]), escape: false);
        }

        return static::withActor($timeline, $activity, $causerName, __('updated the assignee response.'));
    }

    protected static function selfTaskResponsePhrase(TaskAttendeeStatus $status): string
    {
        return match ($status) {
            TaskAttendeeStatus::Accepted => __('accepted the assignment.'),
            TaskAttendeeStatus::Declined => __('declined the assignment.'),
            TaskAttendeeStatus::Tentative => __('said maybe.'),
            TaskAttendeeStatus::InProcess => __('marked the task in process.'),
            TaskAttendeeStatus::Completed => __('marked the task completed.'),
            TaskAttendeeStatus::NeedsAction => __('cleared their response.'),
        };
    }

    protected static function recordedTaskResponseForYouPhrase(TaskAttendeeStatus $status): string
    {
        return match ($status) {
            TaskAttendeeStatus::Accepted => __('recorded that you accepted.'),
            TaskAttendeeStatus::Declined => __('recorded that you declined.'),
            TaskAttendeeStatus::Tentative => __('recorded that you said **maybe**.'),
            TaskAttendeeStatus::InProcess => __('recorded that you marked the task in process.'),
            TaskAttendeeStatus::Completed => __('recorded that you marked the task completed.'),
            TaskAttendeeStatus::NeedsAction => __('recorded that you cleared your response.'),
        };
    }

    protected static function plainDescription(string $update): HtmlString
    {
        return str(str($update)->ucfirst())->inlineMarkdown()->toHtmlString();
    }
}
