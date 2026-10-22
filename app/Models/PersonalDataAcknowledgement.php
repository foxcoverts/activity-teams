<?php

namespace App\Models;

use App\Enums\PersonalDataSection;
use Carbon\CarbonInterface;
use Database\Factories\PersonalDataAcknowledgementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class PersonalDataAcknowledgement extends Model
{
    /** @use HasFactory<PersonalDataAcknowledgementFactory> */
    use HasFactory;

    use MassPrunable;

    public const VALIDITY_MINUTES = 30;

    public const RETENTION_MONTHS = 6;

    public $timestamps = false;

    protected $fillable = [
        'viewer_id',
        'viewer_name',
        'subject_id',
        'subject_name',
        'section',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'section' => PersonalDataSection::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PersonalDataAcknowledgement>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('acknowledged_at', '>', now()->subMinutes(self::VALIDITY_MINUTES));
    }

    public function expiresAt(): CarbonInterface
    {
        return $this->acknowledged_at->copy()->addMinutes(self::VALIDITY_MINUTES);
    }

    /**
     * @return Builder<PersonalDataAcknowledgement>
     */
    public function prunable(): Builder
    {
        return static::query()->where('acknowledged_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }
}
