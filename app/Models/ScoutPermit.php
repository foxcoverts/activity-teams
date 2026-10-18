<?php

namespace App\Models;

use App\Contracts\QualificationType;
use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use Database\Factories\ScoutPermitFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ScoutPermit extends Model implements QualificationType
{
    /** @use HasFactory<ScoutPermitFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'activity',
        'category',
        'permit_type',
        'restrictions',
    ];

    protected function casts(): array
    {
        return [
            'activity' => ScoutPermitActivity::class,
            'category' => ScoutPermitCategory::class,
            'permit_type' => ScoutPermitType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ScoutPermit $permit): void {
            if ($permit->activity === ScoutPermitActivity::Archery) {
                $permit->category = null;
            }

            if ($permit->activity === ScoutPermitActivity::ClimbingAndAbseiling && ! $permit->category instanceof ScoutPermitCategory) {
                throw ValidationException::withMessages([
                    'category' => __('A climbing permit needs a category.'),
                ]);
            }

            if (blank($permit->restrictions)) {
                $permit->restrictions = null;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function summary(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_filter([
                $this->activity?->getLabel(),
                $this->category?->getLabel(),
                $this->permit_type?->getLabel(),
            ]);

            return implode(' — ', $parts);
        });
    }

    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'detail');
    }
}
