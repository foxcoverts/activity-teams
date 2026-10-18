<?php

namespace Tests\Unit;

use App\Enums\GirlguidingLevel;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use PHPUnit\Framework\TestCase;

class QualificationCoverTest extends TestCase
{
    public function test_a_higher_scout_category_covers_the_lower_ones(): void
    {
        $artificialTop = ScoutPermitCategory::ArtificialTopRope;
        $naturalTop = ScoutPermitCategory::NaturalTopRope;
        $artificialLead = ScoutPermitCategory::ArtificialLeadClimbing;
        $naturalLead = ScoutPermitCategory::NaturalLeadClimbing;

        $this->assertTrue($naturalTop->covers($artificialTop));
        $this->assertTrue($artificialLead->covers($artificialTop));
        $this->assertTrue($naturalLead->covers($naturalTop));
        $this->assertTrue($naturalLead->covers($artificialLead));
        $this->assertTrue($naturalLead->covers($artificialTop));

        $this->assertFalse($naturalTop->covers($artificialLead));
        $this->assertFalse($artificialLead->covers($naturalTop));
        $this->assertFalse($artificialTop->covers($naturalTop));
    }

    public function test_mountain_training_cover_is_separate_from_prerequisites(): void
    {
        $cwi = MountainTrainingAward::ClimbingWallInstructor;
        $abseil = MountainTrainingAward::ClimbingWallInstructorAbseil;
        $rci = MountainTrainingAward::RockClimbingInstructor;
        $cwdi = MountainTrainingAward::ClimbingWallDevelopmentInstructor;
        $rcdi = MountainTrainingAward::RockClimbingDevelopmentInstructor;
        $leader = MountainTrainingAward::MountainLeader;
        $mci = MountainTrainingAward::MountaineeringAndClimbingInstructor;
        $wmci = MountainTrainingAward::WinterMountaineeringAndClimbingInstructor;

        $this->assertTrue($rci->covers($cwi));
        $this->assertTrue($rci->covers($abseil));
        $this->assertTrue($mci->covers($cwi));
        $this->assertTrue($mci->covers($abseil));
        $this->assertTrue($mci->covers($rci));
        $this->assertTrue($mci->covers($cwdi));
        $this->assertTrue($mci->covers($rcdi));
        $this->assertTrue($rcdi->covers($cwdi));

        $this->assertFalse($cwi->covers($abseil));
        $this->assertFalse($cwdi->covers($cwi));
        $this->assertFalse($rcdi->covers($rci));
        $this->assertFalse($mci->covers($leader));
        $this->assertFalse($wmci->covers($mci));

        $this->assertSame([$cwi, $rci], $cwdi->prerequisites());
        $this->assertSame([$rci], $rcdi->prerequisites());
        $this->assertSame([$leader], $mci->prerequisites());
        $this->assertSame([$mci], $wmci->prerequisites());
        $this->assertSame([], $rci->prerequisites());
    }

    public function test_a_supervisory_permit_covers_a_leadership_permit(): void
    {
        $this->assertTrue(ScoutPermitType::Supervisory->covers(ScoutPermitType::Leadership));
        $this->assertTrue(ScoutPermitType::Supervisory->covers(ScoutPermitType::Supervisory));
        $this->assertFalse(ScoutPermitType::Leadership->covers(ScoutPermitType::Supervisory));
        $this->assertFalse(ScoutPermitType::Supervisory->covers(ScoutPermitType::Personal));
        $this->assertFalse(ScoutPermitType::Leadership->covers(ScoutPermitType::Personal));
    }

    public function test_girlguiding_level_two_covers_level_one(): void
    {
        $this->assertTrue(GirlguidingLevel::Two->covers(GirlguidingLevel::One));
        $this->assertFalse(GirlguidingLevel::One->covers(GirlguidingLevel::Two));
        $this->assertTrue(GirlguidingLevel::One->covers(GirlguidingLevel::One));
    }
}
