<?php

namespace App\Enums\Incident;

use Filament\Support\Contracts\HasLabel;

enum Injury: string implements HasLabel
{
    case Fatality = 'Death';
    case Amputation = 'Amputation';
    case Crush = 'Crush injury to the head or torso';
    case MajorFracture = 'Fractures (other than to fingers, thumbs, and toes)';
    case Asphyxia = 'Loss of consciousness caused by asphyxia';
    case HeadInjury = 'Loss of consciousness caused by head injury';
    case Blinding = 'Permanent blinding or reduction in sight';
    case Burns = 'Serious burns (including scalding)';
    case Scalping = 'Scalping requiring hospital treatment';
    case Minor = 'Any other injury (please describe in the details below)';

    public function getLabel(): string
    {
        return __($this->value);
    }
}
