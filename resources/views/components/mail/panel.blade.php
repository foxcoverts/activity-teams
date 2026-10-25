@props(['tone' => 'neutral'])

@php
    [$background, $color, $border] = match ($tone) {
        'info' => ['#eff6ff', '#1e3a8a', '#2563eb'],
        'warning' => ['#fffbeb', '#78350f', '#d97706'],
        default => ['#fafafa', '#52525b', '#18181b'],
    };
@endphp

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 21px 0;">
<tr>
<td style="background-color: {{ $background }}; border-left: 4px solid {{ $border }}; color: {{ $color }}; padding: 16px;">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
