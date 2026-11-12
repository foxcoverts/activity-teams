<?php

namespace App\Casts;

use App\Support\DeclinedSessions;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<DeclinedSessions, DeclinedSessions|array|string|null>
 */
class AsDeclinedSessions implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): DeclinedSessions
    {
        return DeclinedSessions::from($value);
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $value instanceof DeclinedSessions && ! is_array($value) && ! is_string($value)) {
            throw new InvalidArgumentException('The declined_sessions attribute must be a DeclinedSessions instance, array, or JSON string.');
        }

        $sessions = DeclinedSessions::from($value);

        if ($sessions->isEmpty()) {
            return [$key => null];
        }

        return [
            $key => json_encode($sessions->toArray(), JSON_THROW_ON_ERROR),
        ];
    }
}
