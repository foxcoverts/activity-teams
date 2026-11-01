<?php

namespace App\Contracts;

use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Commentable
{
    public function comments(): MorphMany;

    public function commentTeam(): ?Team;
}
