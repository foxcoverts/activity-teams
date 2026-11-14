<?php

namespace App\Models;

use Database\Factories\EquipmentSetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EquipmentSet extends Model
{
    /** @use HasFactory<EquipmentSetFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'equipment_set_group_id',
        'name',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function equipmentSetGroup(): BelongsTo
    {
        return $this->belongsTo(EquipmentSetGroup::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class);
    }
}
