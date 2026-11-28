<?php

namespace App\Support;

use App\Models\MerchandiseOrderLine;
use App\Models\MerchandiseSupplierOrder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MerchandiseSupplierOrderExporter
{
    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function table(MerchandiseSupplierOrder $supplierOrder): array
    {
        $supplierOrder->loadMissing([
            'lines.order.user',
            'lines.variant.optionValues.axis',
            'lines.item.optionAxes',
        ]);

        $board = $this->board(
            lines: $supplierOrder->lines,
            selectedLineIds: $supplierOrder->lines->modelKeys(),
            editable: false,
            showOnHandHint: false,
        );

        return [
            'headers' => $board['headers'],
            'rows' => array_map(
                fn (array $row): array => $row['cells'],
                $board['rows'],
            ),
        ];
    }

    public function toCsv(MerchandiseSupplierOrder $supplierOrder): string
    {
        $table = $this->table($supplierOrder);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $table['headers']);

        foreach ($table['rows'] as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public function csvFilename(MerchandiseSupplierOrder $supplierOrder): string
    {
        $supplierOrder->loadMissing('supplier');

        $supplier = Str::slug($supplierOrder->supplier?->name ?? 'supplier') ?: 'supplier';
        $when = $supplierOrder->sent_at
            ?->timezone(config('app.timezone'))
            ?->format('Y-m-d\TH-i-s')
            ?? 'draft';

        return "order-{$supplier}-{$when}.csv";
    }

    /**
     * Unified board / export-shaped table for draft (all candidates + selection) or sent (included only).
     *
     * @param  Collection<int, MerchandiseOrderLine>  $lines
     * @param  list<string>|array<int, string>  $selectedLineIds
     * @return array{
     *     headers: list<string>,
     *     rows: list<array{id: string, selected: bool, cells: list<string|Htmlable>}>,
     *     editable: bool
     * }
     */
    public function board(
        Collection $lines,
        array $selectedLineIds = [],
        bool $editable = false,
        bool $showOnHandHint = false,
    ): array {
        $lines = $this->sortedLines($lines)->values();
        $selected = collect($selectedLineIds)->map(fn (mixed $id): string => (string) $id)->all();
        $includeSupplierCode = $this->anyLineHasSupplierCode($lines);
        $dynamicHeaders = $this->dynamicHeaders($lines);
        $headers = array_merge(
            [__('Member'), __('Item')],
            $includeSupplierCode ? [__('Supplier code')] : [],
            [__('Qty')],
            $dynamicHeaders,
        );

        $rows = $lines
            ->map(function (MerchandiseOrderLine $line) use ($dynamicHeaders, $selected, $showOnHandHint, $includeSupplierCode): array {
                $axisValues = $this->axisValuesByLabel($line);
                $personalisationValues = $this->personalisationValuesByLabel($line);
                $onHand = (int) ($line->variant?->stock_quantity ?? 0);
                $qty = (int) $line->quantity;
                $qtyCell = $showOnHandHint && $onHand > 0
                    ? new HtmlString(
                        '<span class="inline-flex items-baseline justify-center tabular-nums">'
                        .'<span class="invisible select-none" aria-hidden="true"><sub>/'.e((string) $onHand).'</sub></span>'
                        .'<span>'.e((string) $qty).'</span>'
                        .'<sub>/'.e((string) $onHand).'</sub>'
                        .'</span>'
                    )
                    : (string) $qty;

                $cells = [
                    $line->order?->user?->name ?? '',
                    $line->item_title,
                ];

                if ($includeSupplierCode) {
                    $cells[] = (string) ($line->item?->supplier_code ?? '');
                }

                $cells[] = $qtyCell;

                foreach ($dynamicHeaders as $label) {
                    $cells[] = $axisValues[$label] ?? $personalisationValues[$label] ?? '';
                }

                return [
                    'id' => (string) $line->getKey(),
                    'selected' => in_array((string) $line->getKey(), $selected, true),
                    'cells' => $cells,
                ];
            })
            ->values()
            ->all();

        return [
            'headers' => $headers,
            'rows' => $rows,
            'editable' => $editable,
        ];
    }

    /**
     * @param  Collection<int, MerchandiseOrderLine>|iterable<int, MerchandiseOrderLine>  $lines
     * @return Collection<int, MerchandiseOrderLine>
     */
    private function sortedLines(iterable $lines): Collection
    {
        $collection = collect($lines)->values();

        $collection->each(function (MerchandiseOrderLine $line): void {
            $line->loadMissing([
                'order.user',
                'variant.optionValues.axis',
                'item.optionAxes',
            ]);
        });

        return $collection
            ->sortBy([
                fn (MerchandiseOrderLine $line): string => $line->item_title,
                fn (MerchandiseOrderLine $line): string => $line->variant_label,
                fn (MerchandiseOrderLine $line): string => $line->order?->user?->name ?? '',
            ])
            ->values();
    }

    /**
     * @param  Collection<int, MerchandiseOrderLine>  $lines
     */
    private function anyLineHasSupplierCode(Collection $lines): bool
    {
        return $lines->contains(
            fn (MerchandiseOrderLine $line): bool => filled($line->item?->supplier_code),
        );
    }

    /**
     * @param  Collection<int, MerchandiseOrderLine>  $lines
     * @return list<string>
     */
    private function dynamicHeaders(Collection $lines): array
    {
        $labels = [];

        foreach ($lines as $line) {
            foreach ($this->axisValuesByLabel($line) as $label => $value) {
                $labels[$label] = true;
            }

            foreach ($this->personalisationValuesByLabel($line) as $label => $value) {
                $labels[$label] = true;
            }
        }

        return array_keys($labels);
    }

    /**
     * @return array<string, string>
     */
    private function axisValuesByLabel(MerchandiseOrderLine $line): array
    {
        $variant = $line->variant;

        if ($variant === null) {
            return [];
        }

        $variant->loadMissing('optionValues.axis');

        $values = [];

        foreach ($variant->optionValues as $optionValue) {
            $axisName = $optionValue->axis?->name;

            if (blank($axisName)) {
                continue;
            }

            $values[$axisName] = $optionValue->label;
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function personalisationValuesByLabel(MerchandiseOrderLine $line): array
    {
        $values = [];

        foreach ($line->personalisationSnapshot() as $field) {
            $label = $field['label'] ?? null;

            if (! is_string($label) || $label === '') {
                continue;
            }

            $values[$label] = (string) ($field['value'] ?? '');
        }

        return $values;
    }
}
