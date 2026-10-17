<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;

class AsUniqueEnumCollection implements Castable
{
    /**
     * @param  array{class-string}  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class($arguments) implements CastsAttributes
        {
            public function __construct(protected array $arguments) {}

            public function get($model, $key, $value, $attributes): Collection
            {
                if (! isset($attributes[$key]) || $attributes[$key] === '') {
                    return collect();
                }

                $enumClass = $this->arguments[0];

                return collect(explode(',', $attributes[$key]))->map(function ($value) use ($enumClass) {
                    return is_subclass_of($enumClass, BackedEnum::class)
                        ? $enumClass::from($value)
                        : constant($enumClass.'::'.$value);
                })->sort()->unique();
            }

            public function set($model, $key, $value, $attributes): array
            {
                if ($value === null || empty($value)) {
                    return [$key => ''];
                }

                $storable = [];
                foreach ($value as $enum) {
                    $storable[] = $enum instanceof BackedEnum ? $enum->value : $enum;
                }

                return [$key => implode(',', array_unique($storable))];
            }
        };
    }

    public static function of(string $class): string
    {
        return static::class.':'.$class;
    }
}
