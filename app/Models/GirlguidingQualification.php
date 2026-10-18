<?php

namespace App\Models;

use App\Contracts\QualificationType;
use App\Enums\GirlguidingLevel;
use App\Enums\GirlguidingScheme;
use Database\Factories\GirlguidingQualificationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GirlguidingQualification extends Model implements QualificationType
{
    /** @use HasFactory<GirlguidingQualificationFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'scheme',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'scheme' => GirlguidingScheme::class,
            'level' => GirlguidingLevel::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function summary(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_filter([
                $this->scheme?->getLabel(),
                $this->level?->getLabel(),
            ]);

            return implode(' — ', $parts);
        });
    }

    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'detail');
    }
}
