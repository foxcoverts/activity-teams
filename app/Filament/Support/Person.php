<?php

namespace App\Filament\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared User resolution for person Column / Entry (and a future PersonSelect).
 */
final class Person
{
    public static function resolve(mixed $state, ?Model $record = null, ?Model $relatedRecord = null): ?User
    {
        if ($state instanceof User) {
            return $state;
        }

        if ($relatedRecord instanceof User) {
            return $relatedRecord;
        }

        if ($record instanceof User) {
            return $record;
        }

        return null;
    }
}
