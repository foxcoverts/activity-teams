<x-mail::message>
@if ($outstanding !== null)
{{ trans_choice('You have :count new notification.|You have :count new notifications.', $outstanding, ['count' => $outstanding]) }}

@if ($unreadRemainder > 0)
{{ trans_choice('You also have :count unread notification from earlier.|You also have :count unread notifications from earlier.', $unreadRemainder, ['count' => $unreadRemainder]) }}
@endif

<x-mail::button :url="$inboxUrl">
{{ __('Open inbox') }}
</x-mail::button>
@endif

@if (is_array($attentionNew) && $attentionNew !== [])
# {{ __('Needs your attention') }}

{{ __('These tentative bookings need a fresh look.') }}

@foreach ($attentionNew as $line)
- {{ $line }}
@endforeach
@endif

@if (is_array($attentionOutstanding) && $attentionOutstanding !== [])
# {{ __('Still outstanding') }}

{{ __('These still need attention from earlier.') }}

@foreach ($attentionOutstanding as $line)
- {{ $line }}
@endforeach
@endif

@if ((is_array($attentionNew) && $attentionNew !== []) || (is_array($attentionOutstanding) && $attentionOutstanding !== []))
@if ($managerUrl)
<x-mail::button :url="$managerUrl">
{{ __('Open Manager') }}
</x-mail::button>
@endif
@endif

@if (is_array($invitationNudges) && $invitationNudges !== [])
# {{ __('Invitations') }}

{{ __('You still need to reply to these invitations.') }}

@foreach ($invitationNudges as $line)
- {{ $line }}
@endforeach
@endif

@if (is_array($sessionReminders) && $sessionReminders !== [])
# {{ __('Coming up') }}

@foreach ($sessionReminders as $line)
- {{ $line }}
@endforeach
@endif

@if ((is_array($invitationNudges) && $invitationNudges !== []) || (is_array($sessionReminders) && $sessionReminders !== []))
<x-mail::button :url="$dashboardUrl">
{{ is_array($invitationNudges) && $invitationNudges !== [] && (! is_array($sessionReminders) || $sessionReminders === [])
    ? __('View invitations')
    : __('View bookings') }}
</x-mail::button>
@endif

@if (is_array($debriefsDue) && $debriefsDue !== [])
# {{ __('Debriefs still due') }}

@foreach ($debriefsDue as $line)
- {{ $line }}
@endforeach
@endif

@if (is_array($rota))
# {{ __('Your rota') }}

@if ($rota['additions'] !== [])
**{{ __('Added') }}**

@foreach ($rota['additions'] as $line)
- {{ $line }}
@endforeach
@endif

@if ($rota['removals'] !== [])
**{{ __('Removed') }}**

@foreach ($rota['removals'] as $line)
- {{ $line }}
@endforeach
@endif

@if ($rota['remaining'] !== [])
**{{ __('Still on your rota') }}**

@foreach ($rota['remaining'] as $line)
- {{ $line }}
@endforeach
@endif
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
