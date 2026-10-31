<?php

namespace App\Models;

use App\Qualifications\PermitType;
use App\Qualifications\QualificationCatalogue;
use Carbon\CarbonInterface;
use Database\Factories\ActivityQualificationRequirementFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ActivityQualificationRequirement extends Model
{
    /** @use HasFactory<ActivityQualificationRequirementFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'id',
        'activity_audience_id',
        'created_at',
        'updated_at',
        'kind',
        'criteria',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ActivityQualificationRequirement $requirement): void {
            $requirement->criteria = $requirement->normalizedCriteria();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function activityAudience(): BelongsTo
    {
        return $this->belongsTo(ActivityAudience::class);
    }

    public function matches(Qualification $qualification, ?CarbonInterface $asOf = null): bool
    {
        if ($qualification->isExpired($asOf) || $qualification->kind !== $this->kind) {
            return false;
        }

        $catalogue = app(QualificationCatalogue::class);

        if (! is_string($this->kind) || ! $catalogue->hasFamily($this->kind)) {
            return false;
        }

        $family = $catalogue->family($this->kind);
        $payload = $qualification->payload ?? [];
        $criteria = $this->criteria ?? [];
        $groupKey = $family['group_key'];
        $nodeKey = $family['node_key'];
        $heldGroup = is_string($groupKey)
            ? ($payload[$groupKey] ?? null)
            : $catalogue->onlyGroup($this->kind);
        $requiredGroup = is_string($groupKey) ? ($criteria[$groupKey] ?? null) : null;

        if (filled($requiredGroup) && $heldGroup !== $requiredGroup) {
            return false;
        }

        $heldNode = $payload[$nodeKey] ?? null;
        $requiredNode = $criteria[$nodeKey] ?? null;

        if (filled($requiredNode)) {
            if (! is_string($heldNode) || ! is_string($heldGroup) || ! $catalogue->covers($this->kind, $heldGroup, $heldNode, $requiredNode)) {
                return false;
            }
        }

        if ($family['type_dimension']) {
            $heldType = $payload['permit_type'] ?? null;
            $requiredType = $criteria['permit_type'] ?? null;

            if (filled($requiredType) && (! is_string($heldType) || ! $catalogue->permitTypeCovers($heldType, $requiredType))) {
                return false;
            }
        }

        return filled($heldNode);
    }

    protected function summary(): Attribute
    {
        return Attribute::get(function (): string {
            if (! is_string($this->kind) || ! app(QualificationCatalogue::class)->hasFamily($this->kind)) {
                return '';
            }

            $family = app(QualificationCatalogue::class)->family($this->kind);
            $criteria = $this->criteria ?? [];
            $parts = [app(QualificationCatalogue::class)->familySummaryLabel($this->kind)];

            if (is_string($family['group_key'] ?? null) && filled($criteria[$family['group_key']] ?? null)) {
                $parts[] = (string) $criteria[$family['group_key']];
            }

            if (is_string($family['node_key'] ?? null) && filled($criteria[$family['node_key']] ?? null)) {
                $group = is_string($family['group_key'] ?? null) ? ($criteria[$family['group_key']] ?? null) : null;
                $parts[] = app(QualificationCatalogue::class)->nodeLabel(
                    $this->kind,
                    is_string($group) ? $group : null,
                    (string) $criteria[$family['node_key']],
                );
            }

            if ($family['type_dimension'] && filled($criteria['permit_type'] ?? null)) {
                $parts[] = PermitType::label((string) $criteria['permit_type']);
            }

            return implode(' — ', array_filter($parts));
        });
    }

    /**
     * @return array<string, string>
     */
    private function normalizedCriteria(): array
    {
        $catalogue = app(QualificationCatalogue::class);

        if (! is_string($this->kind) || ! $catalogue->hasFamily($this->kind)) {
            return [];
        }

        $family = $catalogue->family($this->kind);
        $criteria = is_array($this->criteria) ? $this->criteria : [];
        $normalized = [];
        $groupKey = $family['group_key'];
        $nodeKey = $family['node_key'];
        $group = is_string($groupKey) && filled($criteria[$groupKey] ?? null)
            ? (string) $criteria[$groupKey]
            : $catalogue->onlyGroup($this->kind);

        if (is_string($groupKey) && filled($criteria[$groupKey] ?? null)) {
            $normalized[$groupKey] = (string) $criteria[$groupKey];
        }

        if (filled($criteria[$nodeKey] ?? null) && is_string($group) && $catalogue->categoryExists($this->kind, $group, (string) $criteria[$nodeKey])) {
            $normalized[$nodeKey] = (string) $criteria[$nodeKey];
        }

        if ($family['type_dimension'] && filled($criteria['permit_type'] ?? null)) {
            $allowed = $catalogue->typesAllowed($this->kind, $group, $normalized[$nodeKey] ?? null);

            if (in_array($criteria['permit_type'], $allowed, true)) {
                $normalized['permit_type'] = (string) $criteria['permit_type'];
            }
        }

        return $normalized;
    }
}
