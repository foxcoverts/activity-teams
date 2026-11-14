<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Personal logbook') }} — {{ $user->name }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            padding: 2rem;
            font-family: Georgia, "Times New Roman", serif;
            color: #111827;
            background: #fff;
        }

        h1 {
            margin: 0 0 0.25rem;
            font-size: 1.75rem;
        }

        .meta {
            margin: 0 0 1.5rem;
            color: #4b5563;
            font-size: 0.95rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 0.55rem 0.4rem;
            text-align: left;
            vertical-align: top;
        }

        th {
            border-bottom-width: 2px;
            font-family: system-ui, sans-serif;
            font-size: 0.75rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .actions {
            margin-bottom: 1.25rem;
        }

        .actions button {
            font: inherit;
            padding: 0.4rem 0.85rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: #f9fafb;
            cursor: pointer;
        }

        @media print {
            body {
                padding: 0;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <h1>{{ __('Personal logbook') }}</h1>
    <p class="meta">{{ $user->name }} · {{ now()->toFormattedDateString() }}</p>

    @if ($rows->isEmpty())
        <p>{{ __('No saved log entries yet.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td>{{ $row[$column] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
