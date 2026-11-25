<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MerchandisePersonalisation
{
    /**
     * Normalise catalogue field definitions: ensure each row has a stable key.
     *
     * @param  array<int, mixed>  $fields
     * @return list<array{key: string, label: string, required: bool}>
     */
    public static function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '') {
                $key = (string) Str::ulid();
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        return array_values($normalized);
    }

    /**
     * Validate and snapshot answers against the item’s field definitions.
     *
     * @param  list<array{key: string, label: string, required: bool}>  $fields
     * @param  array<string, mixed>  $answers  keyed by field key
     * @return list<array{key: string, label: string, value: string}>
     */
    public static function snapshotAnswers(array $fields, array $answers): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $value = trim((string) ($answers[$key] ?? ''));

            if ($field['required'] && $value === '') {
                throw ValidationException::withMessages([
                    "personalisation.{$key}" => __(':label is required.', ['label' => $field['label']]),
                ]);
            }

            if ($value === '') {
                continue;
            }

            $snapshot[] = [
                'key' => $key,
                'label' => $field['label'],
                'value' => $value,
            ];
        }

        return $snapshot;
    }

    /**
     * Stable fingerprint so identical personalisation merges basket lines.
     *
     * @param  list<array{key: string, label: string, value: string}>  $snapshot
     */
    public static function fingerprint(array $snapshot): string
    {
        $pairs = collect($snapshot)
            ->mapWithKeys(fn (array $row): array => [$row['key'] => $row['value']])
            ->sortKeys()
            ->all();

        return hash('xxh128', json_encode($pairs, JSON_THROW_ON_ERROR));
    }
}
