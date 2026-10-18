<?php

namespace App\Contracts;

use App\Models\Qualification;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface QualificationType
{
    public function summary(): Attribute;

    /**
     * @return MorphOne<Qualification, $this>
     */
    public function qualification(): MorphOne;
}
