<?php

namespace App\Models;

use App\Contracts\QualificationType;
use App\Enums\MountainTrainingAward;
use Database\Factories\MountainTrainingQualificationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MountainTrainingQualification extends Model implements QualificationType
{
    /** @use HasFactory<MountainTrainingQualificationFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'award',
    ];

    protected function casts(): array
    {
        return [
            'award' => MountainTrainingAward::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MountainTrainingQualification $detail): void {
            $qualification = $detail->qualification()->first();

            if (! $qualification instanceof Qualification) {
                return;
            }

            $user = User::query()->find($qualification->user_id);

            if ($user instanceof User) {
                $detail->ensureAvailableTo($user, $detail->exists ? $detail->getKey() : null);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function ensureAvailableTo(User $user, ?string $exceptDetailId = null): void
    {
        if (self::isHeldBy($user, $this->award, $exceptDetailId)) {
            throw ValidationException::withMessages([
                'award' => __('This award is already recorded.'),
            ]);
        }
    }

    public static function isHeldBy(User $user, MountainTrainingAward|string $award, ?string $exceptDetailId = null): bool
    {
        if (! $award instanceof MountainTrainingAward) {
            $award = MountainTrainingAward::from($award);
        }

        return Qualification::query()
            ->where('user_id', $user->getKey())
            ->where('detail_type', self::class)
            ->whereHasMorph('detail', [self::class], function ($query) use ($award, $exceptDetailId): void {
                $query->where('award', $award);

                if ($exceptDetailId !== null) {
                    $query->whereKeyNot($exceptDetailId);
                }
            })
            ->exists();
    }

    public function summary(): Attribute
    {
        return Attribute::get(fn (): string => (string) $this->award?->getLabel());
    }

    public function qualification(): MorphOne
    {
        return $this->morphOne(Qualification::class, 'detail');
    }
}
