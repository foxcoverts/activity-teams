<?php

namespace App\Models;

use Database\Factories\EquipmentSetGroupFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentSetGroup extends Model
{
    /** @use HasFactory<EquipmentSetGroupFactory> */
    use HasFactory, HasUlids;

    public const DEFAULT_LABEL = 'Equipment';

    protected $fillable = [
        'team_id',
        'label',
        'rank',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function equipmentSets(): HasMany
    {
        return $this->hasMany(EquipmentSet::class);
    }

    public static function ensureDefaultFor(Team $team): self
    {
        $existing = $team->equipmentSetGroups()->orderBy('rank')->first();

        if ($existing instanceof self) {
            return $existing;
        }

        return $team->equipmentSetGroups()->create([
            'label' => self::DEFAULT_LABEL,
            'rank' => 1,
        ]);
    }

    public static function nextRankFor(Team $team): int
    {
        return (int) $team->equipmentSetGroups()->max('rank') + 1;
    }

    public static function renumberForTeam(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            $groups = $team->equipmentSetGroups()
                ->orderBy('rank')
                ->orderBy('label')
                ->get()
                ->values();

            $groups->each(function (self $group, int $index): void {
                $group->forceFill(['rank' => 1_000_000 + $index])->save();
            });

            $groups->each(function (self $group, int $index): void {
                $group->forceFill(['rank' => $index + 1])->save();
            });
        });
    }

    public function moveUp(): void
    {
        $this->swapWithNeighbour(-1);
    }

    public function moveDown(): void
    {
        $this->swapWithNeighbour(1);
    }

    public function canMoveUp(): bool
    {
        return $this->neighbour(-1) !== null;
    }

    public function canMoveDown(): bool
    {
        return $this->neighbour(1) !== null;
    }

    public function deleteAndReassign(): void
    {
        $this->loadMissing('team');
        $team = $this->team;

        if (! $team instanceof Team) {
            throw ValidationException::withMessages([
                'label' => __('This group cannot be deleted.'),
            ]);
        }

        DB::transaction(function () use ($team): void {
            $remaining = $team->equipmentSetGroups()
                ->whereKeyNot($this->getKey())
                ->orderBy('rank')
                ->get();

            if ($remaining->isEmpty()) {
                throw ValidationException::withMessages([
                    'label' => __('Keep at least one equipment set group.'),
                ]);
            }

            /** @var self $fallback */
            $fallback = $remaining->first();

            $this->equipmentSets()->update([
                'equipment_set_group_id' => $fallback->getKey(),
            ]);

            $this->delete();

            self::renumberForTeam($team->fresh() ?? $team);
        });
    }

    private function swapWithNeighbour(int $direction): void
    {
        $neighbour = $this->neighbour($direction);

        if ($neighbour === null) {
            return;
        }

        DB::transaction(function () use ($neighbour): void {
            $currentRank = $this->rank;
            $neighbourRank = $neighbour->rank;

            $this->forceFill(['rank' => 0])->save();
            $neighbour->forceFill(['rank' => $currentRank])->save();
            $this->forceFill(['rank' => $neighbourRank])->save();
        });
    }

    private function neighbour(int $direction): ?self
    {
        $this->loadMissing('team');

        if ($this->team === null) {
            return null;
        }

        /** @var Collection<int, self> $ordered */
        $ordered = $this->team->equipmentSetGroups()->orderBy('rank')->get();
        $index = $ordered->search(fn (self $group): bool => $group->is($this));

        if ($index === false) {
            return null;
        }

        return $ordered->get($index + $direction);
    }
}
