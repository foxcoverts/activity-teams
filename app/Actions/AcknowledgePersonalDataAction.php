<?php

namespace App\Actions;

use App\Enums\PersonalDataSection;
use App\Models\PersonalDataAcknowledgement;
use App\Models\User;

class AcknowledgePersonalDataAction
{
    public function __invoke(User $viewer, User $subject, PersonalDataSection $section): void
    {
        if ($viewer->hasAcknowledgedPersonalData($subject, $section)) {
            return;
        }

        PersonalDataAcknowledgement::create([
            'viewer_id' => $viewer->getKey(),
            'viewer_name' => $viewer->name,
            'subject_id' => $subject->getKey(),
            'subject_name' => $subject->name,
            'section' => $section,
            'acknowledged_at' => now(),
        ]);
    }
}
