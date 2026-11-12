<?php

namespace App\Livewire;

use App\Actions\InviteAttendeesAction;
use App\Actions\OverrideAttendanceAction;
use App\Enums\BookingAttendeeResponse;
use App\Filament\Support\SubmitsOnModEnter;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\BookingPeople;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\Component;

class BookingKeysPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->loadMissing(['attendees', 'team']);

        $viewer = auth()->user();
        abort_unless(
            $viewer instanceof User
                && $viewer->can('view', $this->booking)
                && ($this->booking->isLead($viewer) || $this->managesBooking($viewer)),
            403,
        );
    }

    #[On('booking-attendance-updated')]
    public function refreshAfterAttendanceChange(): void
    {
        $this->booking->unsetRelation('attendees');
        $this->booking->load('attendees');
    }

    public function contactAction(): Action
    {
        return Action::make('contact')
            ->label(__('Contact'))
            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
            ->color('gray')
            ->modalHeading(fn (array $arguments): HtmlString => new HtmlString(
                view('filament.bookings.contact-modal-heading', [
                    'person' => $this->person($arguments),
                ])->render(),
            ))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'booking-contact-panel'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->authorize(function (array $arguments): bool {
                return $this->canOpenContact($this->attendee($arguments));
            })
            ->modalContent(fn (array $arguments): View => view('filament.bookings.contact-details-host', [
                'booking' => $this->booking,
                'person' => $this->person($arguments),
            ]));
    }

    public function editAttendanceAction(): Action
    {
        return Action::make('editAttendance')
            ->label(__('Edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->authorize(function (array $arguments): bool {
                if (! $this->managesBooking(auth()->user())) {
                    return false;
                }

                $attendance = $this->attendee($arguments)?->attendance;

                return $attendance instanceof BookingAttendance
                    && (bool) auth()->user()?->can('override', $attendance);
            })
            ->fillForm(fn (array $arguments): array => [
                'member_name' => $this->person($arguments)->name,
                'response' => BookingAttendeeResponse::tryFromStatus(
                    $this->attendee($arguments)?->attendance?->status,
                )?->value,
                'comment' => $this->attendee($arguments)?->attendance?->comment,
            ])
            ->schema([
                TextInput::make('member_name')
                    ->label(__('Member'))
                    ->disabled()
                    ->dehydrated(false),
                ToggleButtons::make('response')
                    ->label(__('Status'))
                    ->options(BookingAttendeeResponse::class)
                    ->inline()
                    ->required(),
                Textarea::make('comment')
                    ->extraAlpineAttributes(SubmitsOnModEnter::attributes()),
            ])
            ->action(function (array $arguments, array $data): void {
                $manager = auth()->user();
                $person = $this->person($arguments);
                $response = $data['response'] ?? null;
                $response = $response instanceof BookingAttendeeResponse
                    ? $response
                    : BookingAttendeeResponse::tryFrom((string) $response);

                if (! $manager instanceof User || $response === null) {
                    return;
                }

                app(OverrideAttendanceAction::class)(
                    $this->booking,
                    $person,
                    $manager,
                    $response->toStatus(),
                    $data['comment'] ?? null,
                );

                $this->attendanceChanged();
            });
    }

    public function inviteHolderAction(): Action
    {
        return Action::make('inviteHolder')
            ->label(__('Invite'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('Invite :name?', [
                'name' => $this->person($arguments)->name,
            ]))
            ->modalDescription(__('They will be added to the guest list and sent an invitation.'))
            ->modalSubmitActionLabel(__('Invite'))
            ->authorize(function (array $arguments): bool {
                $person = User::query()->find($arguments['person'] ?? null);

                return $person instanceof User && $this->canInviteHolder($person);
            })
            ->action(function (array $arguments): void {
                $manager = auth()->user();
                $person = $this->person($arguments);

                if (! $manager instanceof User || ! $this->canInviteHolder($person)) {
                    return;
                }

                app(InviteAttendeesAction::class)($this->booking, [$person->getKey()], $manager);

                Notification::make()
                    ->success()
                    ->title(__(':name has been invited.', ['name' => $person->name]))
                    ->send();

                $this->attendanceChanged();
            });
    }

    public function render(): View
    {
        $viewer = auth()->user();
        abort_unless($viewer instanceof User, 403);

        $this->booking->load(['attendees', 'team']);
        $manages = $this->managesBooking($viewer);
        $groups = BookingPeople::keyHolderGroups($this->booking);
        $users = $this->usersForGroups($groups);

        $groups = collect($groups)
            ->map(function (array $group) use ($manages, $users, $viewer): array {
                $group['people'] = array_map(function (array $person) use ($manages, $users, $viewer): array {
                    $user = $users->get($person['id']);
                    $attendee = $this->booking->attendees->find($person['id']);
                    $canPhone = $this->canViewContact($attendee, 'viewPhone');
                    $canEmergency = $this->canViewContact($attendee, 'viewEmergencyContact');
                    $canInvite = $manages && $user instanceof User && $this->canInviteHolder($user);
                    $canEdit = $manages
                        && $attendee?->attendance instanceof BookingAttendance
                        && $viewer->can('override', $attendee->attendance);
                    $canViewMember = $manages
                        && $user instanceof User
                        && MemberResource::canViewAny()
                        && MemberResource::canView($user);

                    return $person + [
                        'can_contact' => $canPhone || $canEmergency,
                        'has_contact_details' => $canPhone
                            || ($canEmergency && filled($attendee?->emergency_phone)),
                        'can_invite' => $canInvite,
                        'can_edit' => $canEdit,
                        'can_view_member' => $canViewMember,
                        'view_member_url' => $canViewMember && $this->booking->team !== null
                            ? MemberResource::getUrl(
                                'view',
                                ['record' => $person['id']],
                                panel: 'teams',
                                tenant: $this->booking->team,
                            )
                            : null,
                    ];
                }, $group['people']);

                return $group;
            })
            ->all();

        return view('filament.bookings.keys', [
            'groups' => $groups,
        ]);
    }

    /**
     * @param  list<array{people: list<array{id: string}>}>  $groups
     * @return Collection<string, User>
     */
    private function usersForGroups(array $groups): Collection
    {
        $ids = collect($groups)
            ->flatMap(fn (array $group): array => $group['people'])
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->getKey());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function person(array $arguments): User
    {
        $person = User::query()->find($arguments['person'] ?? null);
        abort_unless($person instanceof User, 404);

        return $person;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function attendee(array $arguments): ?User
    {
        return $this->booking->attendees()->whereKey($arguments['person'] ?? null)->first();
    }

    private function canInviteHolder(User $person): bool
    {
        if (! $this->managesBooking(auth()->user()) || ! $this->booking->acceptsInvites()) {
            return false;
        }

        $attendee = $this->booking->attendees->find($person->getKey());
        $available = $attendee === null
            || ($attendee->attendance?->isEligibleForReinvite($this->booking) ?? false);

        return $available
            && ! $person->isSuspended()
            && $person->hasVerifiedEmail()
            && $this->booking->team !== null
            && $person->isCurrentMemberOf($this->booking->team);
    }

    private function managesBooking(?User $viewer): bool
    {
        return $viewer instanceof User
            && $this->booking->team !== null
            && $viewer->manages($this->booking->team);
    }

    private function canOpenContact(?User $person): bool
    {
        return $this->canViewContact($person, 'viewPhone')
            || $this->canViewContact($person, 'viewEmergencyContact');
    }

    private function canViewContact(?User $person, string $ability): bool
    {
        $actor = auth()->user();
        $attendance = $person?->attendance;

        if (! $actor instanceof User || ! $person instanceof User || $attendance === null) {
            return false;
        }

        if (! $actor->can($ability, $attendance)) {
            return false;
        }

        if ($ability === 'viewPhone') {
            return filled($person->phone);
        }

        return true;
    }

    private function attendanceChanged(): void
    {
        $this->booking = $this->booking->fresh(['attendees', 'team']) ?? $this->booking;
        $this->dispatch('booking-attendance-updated');
    }
}
