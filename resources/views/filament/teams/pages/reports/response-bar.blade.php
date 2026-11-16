@php
    use App\Enums\BookingAttendeeStatus;
    use App\Support\Palette;

    $segments = [
        [
            'key' => 'accepted',
            'share' => $row['accepted_share'],
            'count' => $row['accepted'],
            'label' => BookingAttendeeStatus::Accepted->getLabel(),
            'color' => Palette::positive()[500],
        ],
        [
            'key' => 'tentative',
            'share' => $row['tentative_share'],
            'count' => $row['tentative'],
            'label' => BookingAttendeeStatus::Tentative->getLabel(),
            'color' => Palette::uncertain()[500],
        ],
        [
            'key' => 'declined',
            'share' => $row['declined_share'],
            'count' => $row['declined'],
            'label' => BookingAttendeeStatus::Declined->getLabel(),
            'color' => Palette::destructive()[500],
        ],
        [
            'key' => 'awaiting',
            'share' => $row['awaiting_share'],
            'count' => $row['awaiting'],
            'label' => BookingAttendeeStatus::NeedsAction->getLabel(),
            'color' => Palette::neutral()[500],
        ],
    ];

    $lines = collect($segments)
        ->filter(fn (array $segment): bool => $segment['count'] > 0)
        ->map(fn (array $segment): string => $segment['label'].': '.$segment['count'].' ('.rtrim(rtrim(number_format($segment['share'], 1), '0'), '.').'%)');

    $title = $lines->implode(' · ');
    $titleHtml = $lines
        ->map(fn (string $line): string => e($line))
        ->implode('<br>');

    $maxInvites = max(1, (int) ($maxInvites ?? $row['invites']));
    $barWidth = $row['invites'] > 0
        ? round(($row['invites'] / $maxInvites) * 100, 2)
        : 0;
@endphp

@if ($row['invites'] === 0)
    <span class="text-gray-500 dark:text-gray-400">—</span>
@else
    <div class="h-3 w-full">
        <div
            class="flex h-full cursor-help overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
            style="width: {{ $barWidth }}%"
            x-data
            x-tooltip.html.raw="{!! $titleHtml !!}"
            role="img"
            aria-label="{{ $title }}"
        >
            @foreach ($segments as $segment)
                @continue($segment['share'] <= 0)
                <span
                    class="h-full"
                    style="width: {{ $segment['share'] }}%; background-color: {{ $segment['color'] }}"
                    wire:key="response-bar-{{ $row['user']->getKey() }}-{{ $segment['key'] }}"
                ></span>
            @endforeach
        </div>
    </div>
@endif
