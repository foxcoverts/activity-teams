<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class KeyCover extends Pivot
{
    public $incrementing = false;

    protected $table = 'key_covers';

    protected static function booted(): void
    {
        static::creating(function (KeyCover $cover): void {
            if ($cover->key_id === $cover->covered_key_id) {
                throw ValidationException::withMessages([
                    'covers' => __('A key cannot cover itself.'),
                ]);
            }
        });
    }
}
