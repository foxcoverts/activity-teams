@php
    /** @var array{headers: list<string>, rows: list<array{id?: string, selected?: bool, cells: list<string>}|list<string>>, editable?: bool} $table */
    $editable = (bool) ($table['editable'] ?? false);
    $headers = $table['headers'] ?? [];
    $rows = $table['rows'] ?? [];
    $selectableRows = collect($rows)->filter(
        fn (mixed $row): bool => is_array($row) && filled($row['id'] ?? null),
    );
    $selectedCount = $selectableRows->where('selected', true)->count();
    $totalCount = $selectableRows->count();
    $allSelected = $totalCount > 0 && $selectedCount === $totalCount;
    $someSelected = $selectedCount > 0 && $selectedCount < $totalCount;
    $selectionKey = $selectableRows
        ->map(fn (array $row): string => ($row['id'] ?? '').':'.(($row['selected'] ?? false) ? '1' : '0'))
        ->implode('|');
@endphp

@if ($rows === [])
    <p class="flush-table-panel__empty text-sm text-gray-500 dark:text-gray-400">
        {{ $editable ? __('No approved lines need ordering from this supplier.') : __('No lines on this supplier order.') }}
    </p>
@else
    <div
        wire:key="supplier-order-board-{{ $editable ? 'draft' : 'sent' }}-{{ md5($selectionKey) }}"
        class="flush-table-panel__table overflow-x-auto"
    >
        <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    @if ($editable)
                        <th class="w-10 px-3 py-2 text-start">
                            <input
                                type="checkbox"
                                wire:key="supplier-order-select-all-{{ $selectedCount }}-{{ $totalCount }}"
                                wire:click.prevent="toggleSelectAll"
                                @checked($allSelected)
                                x-data
                                x-init="$el.indeterminate = @js($someSelected)"
                                class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm outline-none transition duration-75 focus:ring-2 focus:ring-primary-600 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:checked:border-primary-500 dark:checked:bg-primary-500"
                                aria-label="{{ __('Select all') }}"
                            />
                        </th>
                    @endif
                    @foreach ($headers as $index => $header)
                        <th @class([
                            'px-3 py-2 text-sm font-semibold text-gray-950 dark:text-white',
                            'text-center' => $index === 2,
                            'text-start' => $index !== 2,
                        ])>
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $row)
                    @php
                        $isStructured = is_array($row) && array_key_exists('cells', $row);
                        $cells = $isStructured ? $row['cells'] : $row;
                        $lineId = $isStructured ? ($row['id'] ?? null) : null;
                        $selected = $isStructured ? (bool) ($row['selected'] ?? false) : false;
                    @endphp
                    <tr @class([
                        'bg-primary-50/40 dark:bg-primary-400/10' => $editable && $selected,
                    ])>
                        @if ($editable && filled($lineId))
                            <td class="px-3 py-2 align-middle">
                                <input
                                    type="checkbox"
                                    wire:key="supplier-order-line-{{ $lineId }}-{{ $selected ? '1' : '0' }}"
                                    wire:click.prevent="toggleLine('{{ $lineId }}')"
                                    @checked($selected)
                                    class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm outline-none transition duration-75 focus:ring-2 focus:ring-primary-600 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:checked:border-primary-500 dark:checked:bg-primary-500"
                                />
                            </td>
                        @endif
                        @foreach ($cells as $index => $cell)
                            <td @class([
                                'px-3 py-2 text-gray-950 dark:text-white',
                                'text-center' => $index === 2,
                            ])>
                                @if ($cell instanceof \Illuminate\Contracts\Support\Htmlable)
                                    {!! $cell !!}
                                @else
                                    {{ $cell !== '' ? $cell : '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
