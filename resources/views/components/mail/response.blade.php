@props(['status'])

@php
    [$background, $color] = match ($status) {
        \App\Enums\BookingAttendeeStatus::Accepted,
        \App\Enums\TaskAttendeeStatus::Accepted,
        \App\Enums\TaskAttendeeStatus::Completed => ['#f0f9ff', '#0c4a6e'],
        \App\Enums\BookingAttendeeStatus::Tentative,
        \App\Enums\TaskAttendeeStatus::Tentative,
        \App\Enums\TaskAttendeeStatus::InProcess => ['#fffbeb', '#78350f'],
        \App\Enums\BookingAttendeeStatus::Declined,
        \App\Enums\TaskAttendeeStatus::Declined => ['#f1f5f9', '#1e293b'],
        default => ['#f4f4f5', '#3f3f46'],
    };
@endphp

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 16px 0;">
<tr>
<td style="background-color: {{ $background }}; color: {{ $color }}; padding: 16px; border-radius: 8px;">
{{ $slot }}
</td>
</tr>
</table>
