@php
    $formatNumber = function (int|float|null $value, bool $percent = false, bool $hours = false): string {
        if ($value === null) {
            return '—';
        }

        if ($percent) {
            return number_format($value * 100, 0).'%';
        }

        if ($hours) {
            return number_format($value, $value == floor($value) ? 0 : 1);
        }

        return number_format($value);
    };

    $formatDelta = function (array $metric, bool $percent = false, bool $hours = false) use ($formatNumber): string {
        $absolute = $metric['absolute'] ?? null;

        if ($absolute === null) {
            return '—';
        }

        $sign = $absolute > 0 ? '+' : '';
        $value = $percent
            ? $sign.number_format($absolute * 100, 0).' pp'
            : $sign.$formatNumber($absolute, hours: $hours);

        if (($metric['percent'] ?? null) !== null && ! $percent) {
            $value .= ' ('.$sign.number_format($metric['percent'], 0).'%)';
        }

        return $value;
    };
@endphp

<div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
        {{ $formatNumber($metric['current'] ?? null, $percent ?? false, $hours ?? false) }}
    </p>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
        {{ __('vs :year: :previous', [
            'year' => $previousYear,
            'previous' => $formatNumber($metric['previous'] ?? null, $percent ?? false, $hours ?? false),
        ]) }}
        <span class="text-gray-500 dark:text-gray-400">· {{ $formatDelta($metric, $percent ?? false, $hours ?? false) }}</span>
    </p>
    @isset($hint)
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endisset
</div>
