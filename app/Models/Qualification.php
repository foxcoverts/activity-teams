<?php

namespace App\Models;

use App\Contracts\QualificationType;
use Carbon\CarbonInterface;
use Database\Factories\QualificationFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Qualification extends Model
{
    /** @use HasFactory<QualificationFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'expires_on',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Qualification $qualification): void {
            if ($qualification->detail_type === MountainTrainingQualification::class) {
                $qualification->expires_on = null;

                return;
            }

            if ($qualification->expires_on === null) {
                throw ValidationException::withMessages([
                    'expires_on' => __('An expiry date is required.'),
                ]);
            }
        });

        static::deleting(function (Qualification $qualification): void {
            $detail = $qualification->relationLoaded('detail')
                ? $qualification->detail
                : $qualification->detail()->first();

            $detail?->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public static function record(User $user, Model&QualificationType $detail, mixed $expiresOn = null): self
    {
        if ($detail instanceof MountainTrainingQualification) {
            $detail->ensureAvailableTo($user);
            $expiresOn = null;
        } elseif ($expiresOn === null || $expiresOn === '') {
            throw ValidationException::withMessages([
                'expires_on' => __('An expiry date is required.'),
            ]);
        }

        $detail->save();

        $qualification = new self;
        $qualification->user()->associate($user);
        $qualification->detail()->associate($detail);
        $qualification->expires_on = $expiresOn;
        $qualification->save();

        return $qualification->load('detail');
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

    public function isExpired(): bool
    {
        if (! $this->expires_on instanceof CarbonInterface) {
            return false;
        }

        return $this->expires_on->copy()->endOfDay()->isPast();
    }

    public function scopeInDate(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('expires_on')
                ->orWhereDate('expires_on', '>=', now()->toDateString());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detail(): MorphTo
    {
        return $this->morphTo();
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->detail_type) {
            ScoutPermit::class => __('Scout permit'),
            MountainTrainingQualification::class => __('Mountain Training'),
            GirlguidingQualification::class => __('Girlguiding'),
            default => '',
        });
    }

    protected function summary(): Attribute
    {
        return Attribute::get(function (): string {
            $detail = $this->detail;

            if (! $detail instanceof QualificationType) {
                return '';
            }

            return (string) $detail->summary;
        });
    }

    protected function restrictionNote(): Attribute
    {
        return Attribute::get(function (): ?string {
            $detail = $this->detail;

            if (! $detail instanceof ScoutPermit || blank($detail->restrictions)) {
                return null;
            }

            return $detail->restrictions;
        });
    }
}
