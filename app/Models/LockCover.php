<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class LockCover extends Pivot
{
    public $incrementing = false;

    protected $table = 'lock_covers';

    protected static function booted(): void
    {
        static::creating(function (LockCover $cover): void {
            if ($cover->lock_id === $cover->covered_lock_id) {
                throw ValidationException::withMessages([
                    'covers' => __('This lock cannot open itself.'),
                ]);
            }
        });
    }
}
