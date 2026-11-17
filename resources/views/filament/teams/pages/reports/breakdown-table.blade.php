@php
    $showParticipants ??= false;
    $formatDelta = function (array $metric): string {
        $absolute = $metric['absolute'];
        $sign = $absolute > 0 ? '+' : '';

        return $sign.number_format($absolute);
    };
@endphp

@if ($rows->isEmpty())
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No bookings in this year.') }}</p>
@else
    <div class="overflow-x-auto">
        <table @class([
            'w-full table-fixed text-sm',
            'min-w-[36rem]' => ! $showParticipants,
            'min-w-[48rem]' => $showParticipants,
        ])>
            <colgroup>
                @if ($showParticipants)
                    <col class="w-[22%]" />
                    <col class="w-[10%]" />
                    <col class="w-[10%]" />
                    <col class="w-[10%]" />
                    <col class="w-[10%]" />
                    <col class="w-[10%]" />
                    <col class="w-[14%]" />
                    <col class="w-[14%]" />
                @else
                    <col class="w-[28%]" />
                    <col class="w-[12%]" />
                    <col class="w-[12%]" />
                    <col class="w-[12%]" />
                    <col class="w-[18%]" />
                    <col class="w-[18%]" />
                @endif
            </colgroup>
            <thead>
                <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th scope="col" class="py-2 pr-3 text-start font-medium">{{ $heading }}</th>
                    <th scope="col" class="py-2 px-2 text-end font-medium">{{ $year }}</th>
                    <th scope="col" class="py-2 px-2 text-end font-medium">{{ $year - 1 }}</th>
                    <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Δ') }}</th>
                    @if ($showParticipants)
                        <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Participants :year', ['year' => $year]) }}</th>
                        <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Participants :year', ['year' => $year - 1]) }}</th>
                    @endif
                    <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Cancelled :year', ['year' => $year]) }}</th>
                    <th scope="col" class="py-2 pl-2 text-end font-medium">{{ __('Cancelled :year', ['year' => $year - 1]) }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $row)
                    <tr wire:key="report-{{ $dimension }}-{{ $row['key'] ?? 'none' }}">
                        <td class="py-2 pr-3 font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                        <td class="py-2 px-2 text-end tabular-nums">{{ number_format($row['confirmed']['current']) }}</td>
                        <td class="py-2 px-2 text-end tabular-nums">{{ number_format($row['confirmed']['previous']) }}</td>
                        <td class="py-2 px-2 text-end tabular-nums text-gray-600 dark:text-gray-300">{{ $formatDelta($row['confirmed']) }}</td>
                        @if ($showParticipants)
                            <td class="py-2 px-2 text-end tabular-nums">{{ number_format($row['participants']['current']) }}</td>
                            <td class="py-2 px-2 text-end tabular-nums">{{ number_format($row['participants']['previous']) }}</td>
                        @endif
                        <td class="py-2 px-2 text-end tabular-nums">{{ number_format($row['cancelled']['current']) }}</td>
                        <td class="py-2 pl-2 text-end tabular-nums">{{ number_format($row['cancelled']['previous']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
