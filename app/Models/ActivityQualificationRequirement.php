<?php

namespace App\Models;

use App\Enums\GirlguidingLevel;
use App\Enums\GirlguidingScheme;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use Database\Factories\ActivityQualificationRequirementFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ActivityQualificationRequirement extends Model
{
    /** @use HasFactory<ActivityQualificationRequirementFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'id',
        'activity_id',
        'created_at',
        'updated_at',
        'detail_type',
        'permit_activity',
        'category',
        'permit_type',
        'award',
        'scheme',
        'level',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'permit_activity' => 'array',
            'category' => 'array',
            'permit_type' => 'array',
            'award' => 'array',
            'scheme' => 'array',
            'level' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ActivityQualificationRequirement $requirement): void {
            foreach (['permit_activity', 'category', 'permit_type', 'award', 'scheme', 'level'] as $field) {
                $requirement->{$field} = self::values($requirement->{$field});
            }

            if ($requirement->detail_type !== ScoutPermit::class) {
                $requirement->permit_activity = null;
                $requirement->category = null;
                $requirement->permit_type = null;
            } elseif (! self::allowsClimbingCategory($requirement->permit_activity)) {
                $requirement->category = null;
            }

            if ($requirement->detail_type !== MountainTrainingQualification::class) {
                $requirement->award = null;
            }

            if ($requirement->detail_type !== GirlguidingQualification::class) {
                $requirement->scheme = null;
                $requirement->level = null;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function matches(Qualification $qualification): bool
    {
        if ($qualification->isExpired() || $qualification->detail_type !== $this->detail_type) {
            return false;
        }

        $detail = $qualification->relationLoaded('detail')
            ? $qualification->detail
            : $qualification->detail()->first();

        return match ($this->detail_type) {
            ScoutPermit::class => $detail instanceof ScoutPermit
                && $this->coversAny($detail->activity, $this->permit_activity)
                && $this->coversAny($detail->category, $this->category)
                && $this->coversAny($detail->permit_type, $this->permit_type),
            MountainTrainingQualification::class => $detail instanceof MountainTrainingQualification
                && $this->coversAny($detail->award, $this->award),
            GirlguidingQualification::class => $detail instanceof GirlguidingQualification
                && $this->coversAny($detail->scheme, $this->scheme)
                && $this->coversAny($detail->level, $this->level),
            default => false,
        };
    }

    protected function summary(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = match ($this->detail_type) {
                ScoutPermit::class => [
                    __('Scout permit'),
                    ...$this->labels($this->permit_activity, ScoutPermitActivity::class),
                    ...$this->labels($this->category, ScoutPermitCategory::class),
                    ...$this->labels($this->permit_type, ScoutPermitType::class),
                ],
                MountainTrainingQualification::class => [
                    __('Mountain Training'),
                    ...$this->labels($this->award, MountainTrainingAward::class),
                ],
                GirlguidingQualification::class => [
                    __('Girlguiding'),
                    ...$this->labels($this->scheme, GirlguidingScheme::class),
                    ...$this->labels($this->level, GirlguidingLevel::class),
                ],
                default => [],
            };

            return implode(' — ', array_filter($parts));
        });
    }

    /**
     * A blank activity list means any permit activity, including climbing.
     */
    public static function allowsClimbingCategory(mixed $activities): bool
    {
        if (blank($activities)) {
            return true;
        }

        foreach (Arr::wrap($activities) as $activity) {
            if (ScoutPermitActivity::isClimbing($activity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>|null  $values
     */
    private static function values(?array $values): ?array
    {
        if (blank($values)) {
            return null;
        }

        $normalized = [];

        foreach (Arr::wrap($values) as $value) {
            if ($value instanceof \BackedEnum) {
                $normalized[] = $value->value;
            } elseif (is_string($value) && $value !== '') {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param  array<int, mixed>|null  $allowed
     */
    private function coversAny(mixed $held, ?array $allowed): bool
    {
        if (blank($allowed)) {
            return true;
        }

        if (! is_object($held) || ! method_exists($held, 'covers')) {
            return false;
        }

        $heldClass = $held::class;

        foreach ($allowed as $value) {
            $required = $value instanceof $heldClass
                ? $value
                : (is_string($value) ? $held::tryFrom($value) : null);

            if ($required instanceof $heldClass && $held->covers($required)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>|null  $values
     * @param  class-string  $enum
     * @return list<string>
     */
    private function labels(?array $values, string $enum): array
    {
        if (blank($values) || ! method_exists($enum, 'tryFrom')) {
            return [];
        }

        $labels = [];

        foreach ($values as $value) {
            $case = is_string($value) ? $enum::tryFrom($value) : $value;

            if (is_object($case) && method_exists($case, 'getLabel')) {
                $labels[] = $case->getLabel();
            }
        }

        if ($labels === []) {
            return [];
        }

        return [implode(' or ', $labels)];
    }
}
