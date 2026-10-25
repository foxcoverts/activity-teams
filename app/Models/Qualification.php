<?php

namespace App\Models;

use App\Qualifications\PermitType;
use App\Qualifications\QualificationCatalogue;
use App\Support\Palette;
use Carbon\CarbonInterface;
use Database\Factories\QualificationFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Qualification extends Model
{
    /** @use HasFactory<QualificationFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'kind',
        'payload',
        'expires_on',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Qualification $qualification): void {
            $qualification->normalize();
            $qualification->assertValid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(User $user, string $kind, array $payload, mixed $expiresOn = null): self
    {
        $qualification = new self;
        $qualification->user()->associate($user);
        $qualification->kind = $kind;
        $qualification->payload = $payload;
        $qualification->expires_on = $expiresOn;
        $qualification->save();

        return $qualification;
    }

    public static function canMaintain(User $actor, User $member): bool
    {
        if ($actor->isSuspended() || $member->isSuspended() || $member->trashed()) {
            return false;
        }

        $tenant = Filament::getTenant();

        if ($actor->isSystemAdmin() && $tenant instanceof Team && $member->isCurrentMemberOf($tenant)) {
            return true;
        }

        $teamIds = $actor->managedTeams()->pluck('teams.id');

        if ($teamIds->isEmpty()) {
            return false;
        }

        return $member->teams()->whereKey($teamIds)->exists();
    }

    public function isExpired(?CarbonInterface $asOf = null): bool
    {
        if (! $this->expires_on instanceof CarbonInterface) {
            return false;
        }

        return $this->expires_on->toDateString() < ($asOf ?? now())->toDateString();
    }

    /**
     * @return array<int, string>|null
     */
    public function expiryColor(): ?array
    {
        if (! $this->expires_on instanceof CarbonInterface) {
            return null;
        }

        if ($this->isExpired()) {
            return Palette::destructive();
        }

        if ($this->expires_on->lte(now()->addMonths(3)->startOfDay())) {
            return Palette::uncertain();
        }

        return Palette::positive();
    }

    public function scopeInDate(Builder $query, ?CarbonInterface $asOf = null): void
    {
        $date = ($asOf ?? now())->toDateString();

        $query->where(function (Builder $query) use ($date): void {
            $query->whereNull('expires_on')
                ->orWhereDate('expires_on', '>=', $date);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if (! app(QualificationCatalogue::class)->hasFamily((string) $this->kind)) {
                return '';
            }

            return app(QualificationCatalogue::class)->familySummaryLabel((string) $this->kind);
        });
    }

    protected function summary(): Attribute
    {
        return Attribute::get(function (): string {
            return implode(' — ', array_filter($this->summaryParts()));
        });
    }

    protected function restrictionNote(): Attribute
    {
        return Attribute::get(function (): ?string {
            $restrictions = $this->payload['restrictions'] ?? null;

            return filled($restrictions) ? (string) $restrictions : null;
        });
    }

    /**
     * @return list<string>
     */
    public function summaryParts(): array
    {
        if (! app(QualificationCatalogue::class)->hasFamily((string) $this->kind)) {
            return [];
        }

        $family = app(QualificationCatalogue::class)->family((string) $this->kind);
        $payload = $this->payload ?? [];
        $parts = [];

        if (is_string($family['group_key'] ?? null) && filled($payload[$family['group_key']] ?? null)) {
            $parts[] = (string) $payload[$family['group_key']];
        }

        if (is_string($family['node_key'] ?? null) && filled($payload[$family['node_key']] ?? null)) {
            $group = is_string($family['group_key'] ?? null) ? ($payload[$family['group_key']] ?? null) : null;
            $parts[] = app(QualificationCatalogue::class)->nodeLabel(
                (string) $this->kind,
                is_string($group) ? $group : null,
                (string) $payload[$family['node_key']],
            );
        }

        if ($family['type_dimension'] && filled($payload['permit_type'] ?? null)) {
            $parts[] = PermitType::label((string) $payload['permit_type']);
        }

        return $parts;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function detailFields(): array
    {
        if (! app(QualificationCatalogue::class)->hasFamily((string) $this->kind)) {
            return [];
        }

        $family = app(QualificationCatalogue::class)->family((string) $this->kind);
        $payload = $this->payload ?? [];
        $fields = [];

        if (is_string($family['group_key'] ?? null) && filled($payload[$family['group_key']] ?? null)) {
            $fields[] = [
                'label' => $family['group_key'] === 'scheme' ? __('Scheme') : __('Activity'),
                'value' => (string) $payload[$family['group_key']],
            ];
        }

        if (is_string($family['node_key'] ?? null) && filled($payload[$family['node_key']] ?? null)) {
            $fields[] = [
                'label' => match ($family['node_key']) {
                    'award' => __('Award'),
                    'level' => __('Level'),
                    default => __('Category'),
                },
                'value' => app(QualificationCatalogue::class)->nodeLabel(
                    (string) $this->kind,
                    is_string($family['group_key'] ?? null) && is_string($payload[$family['group_key']] ?? null)
                        ? $payload[$family['group_key']]
                        : null,
                    (string) $payload[$family['node_key']],
                ),
            ];
        }

        if ($family['type_dimension'] && filled($payload['permit_type'] ?? null)) {
            $fields[] = [
                'label' => __('Permit type'),
                'value' => PermitType::label((string) $payload['permit_type']),
            ];
        }

        if ($family['restrictions']) {
            $fields[] = [
                'label' => __('Restrictions'),
                'value' => filled($payload['restrictions'] ?? null) ? (string) $payload['restrictions'] : '—',
            ];
        }

        return $fields;
    }

    private function normalize(): void
    {
        $catalogue = app(QualificationCatalogue::class);

        if (! is_string($this->kind) || ! $catalogue->hasFamily($this->kind)) {
            return;
        }

        $family = $catalogue->family($this->kind);
        $payload = is_array($this->payload) ? $this->payload : [];

        if (! $family['expires']) {
            $this->expires_on = null;
        }

        if ($family['restrictions']) {
            if (blank($payload['restrictions'] ?? null)) {
                unset($payload['restrictions']);
            }
        } else {
            unset($payload['restrictions']);
        }

        if (! $family['type_dimension']) {
            unset($payload['permit_type']);
        }

        $this->payload = $payload;
    }

    private function assertValid(): void
    {
        $catalogue = app(QualificationCatalogue::class);

        if (! is_string($this->kind) || ! $catalogue->hasFamily($this->kind)) {
            throw ValidationException::withMessages([
                'kind' => __('Choose a qualification type.'),
            ]);
        }

        $family = $catalogue->family($this->kind);
        $payload = is_array($this->payload) ? $this->payload : [];

        if ($family['expires'] && $this->expires_on === null) {
            throw ValidationException::withMessages([
                'expires_on' => __('An expiry date is required.'),
            ]);
        }

        if (! $catalogue->isValidPayload($this->kind, $payload)) {
            $nodeKey = $family['node_key'];

            throw ValidationException::withMessages([
                $nodeKey => __('Choose a valid qualification from the catalogue.'),
            ]);
        }

        if ($family['unique']) {
            $this->assertUniqueNode($family['node_key']);
        }
    }

    private function assertUniqueNode(string $nodeKey): void
    {
        $value = $this->payload[$nodeKey] ?? null;

        if (! is_string($value) || ! $this->user_id) {
            return;
        }

        $taken = self::query()
            ->where('user_id', $this->user_id)
            ->where('kind', $this->kind)
            ->where('payload->'.$nodeKey, $value)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                $nodeKey => __('This award is already recorded.'),
            ]);
        }
    }
}
