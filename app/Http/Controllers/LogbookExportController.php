<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Logbook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LogbookExportController extends Controller
{
    public function pdf(Request $request, Logbook $logbook): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $filters = $logbook->filtersFromRequest($request);

        $entries = $logbook->applyEntryFilters(
            $logbook->entriesQuery($user),
            $filters,
        )
            ->with(['booking.venue', 'booking.activity', 'booking.audience'])
            ->get();

        $rows = $entries->map(function ($entry) use ($logbook): array {
            $values = $logbook->exportRow($entry);
            $headers = $logbook->exportHeaders();

            return collect($headers)
                ->combine($values)
                ->filter(fn (?string $value): bool => filled($value))
                ->all();
        });

        $columns = collect($logbook->exportHeaders())
            ->filter(fn (string $header): bool => $rows->contains(fn (array $row): bool => array_key_exists($header, $row)))
            ->values()
            ->all();

        return view('logbook.export-pdf', [
            'user' => $user,
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }
}
