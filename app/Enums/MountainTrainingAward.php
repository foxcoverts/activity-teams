<?php

namespace App\Enums;

use App\Enums\Concerns\CoversValues;
use Filament\Support\Contracts\HasLabel;

enum MountainTrainingAward: string implements HasLabel
{
    use CoversValues;

    case IndoorClimbingAssistant = 'ICA';
    case ClimbingWallInstructor = 'CWI';
    case ClimbingWallInstructorAbseil = 'CWI Abseil';
    case ClimbingWallDevelopmentInstructor = 'CWDI';
    case RockClimbingInstructor = 'RCI';
    case RockClimbingDevelopmentInstructor = 'RCDI';
    case MountainLeader = 'ML';
    case MountaineeringAndClimbingInstructor = 'MCI';
    case WinterMountaineeringAndClimbingInstructor = 'WMCI';

    public function getLabel(): string
    {
        return match ($this) {
            self::IndoorClimbingAssistant => __('Indoor Climbing Assistant'),
            self::ClimbingWallInstructor => __('Climbing Wall Instructor'),
            self::ClimbingWallInstructorAbseil => __('Climbing Wall Instructor (Abseil)'),
            self::ClimbingWallDevelopmentInstructor => __('Climbing Wall Development Instructor'),
            self::RockClimbingInstructor => __('Rock Climbing Instructor'),
            self::RockClimbingDevelopmentInstructor => __('Rock Climbing Development Instructor'),
            self::MountainLeader => __('Mountain Leader'),
            self::MountaineeringAndClimbingInstructor => __('Mountaineering and Climbing Instructor'),
            self::WinterMountaineeringAndClimbingInstructor => __('Winter Mountaineering and Climbing Instructor'),
        };
    }

    /**
     * Any one of these is enough. A prerequisite does not count as cover.
     *
     * @return list<self>
     */
    public function prerequisites(): array
    {
        return match ($this) {
            self::ClimbingWallDevelopmentInstructor => [
                self::ClimbingWallInstructor,
                self::RockClimbingInstructor,
            ],
            self::RockClimbingDevelopmentInstructor => [
                self::RockClimbingInstructor,
            ],
            self::MountaineeringAndClimbingInstructor => [
                self::MountainLeader,
            ],
            self::WinterMountaineeringAndClimbingInstructor => [
                self::MountaineeringAndClimbingInstructor,
            ],
            default => [],
        };
    }

    /**
     * @return list<static>
     */
    protected function directlyCovers(): array
    {
        return match ($this) {
            self::RockClimbingInstructor => [
                self::ClimbingWallInstructor,
                self::ClimbingWallInstructorAbseil,
            ],
            self::RockClimbingDevelopmentInstructor => [
                self::ClimbingWallDevelopmentInstructor,
            ],
            self::MountaineeringAndClimbingInstructor => [
                self::ClimbingWallInstructor,
                self::ClimbingWallInstructorAbseil,
                self::RockClimbingInstructor,
                self::ClimbingWallDevelopmentInstructor,
                self::RockClimbingDevelopmentInstructor,
            ],
            default => [],
        };
    }
}
