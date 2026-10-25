<?php

namespace Tests\Unit;

use App\Qualifications\QualificationCatalogue;
use Tests\TestCase;

class QualificationCatalogueTest extends TestCase
{
    private QualificationCatalogue $catalogue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue = app(QualificationCatalogue::class);
    }

    public function test_a_higher_climbing_category_covers_the_lower_ones_in_the_same_group(): void
    {
        $kind = 'scout_permit';
        $group = 'Climbing and Abseiling';

        $this->assertTrue($this->catalogue->covers($kind, $group, 'Natural Top Rope', 'Artificial Top Rope'));
        $this->assertTrue($this->catalogue->covers($kind, $group, 'Artificial Lead Climbing', 'Artificial Top Rope'));
        $this->assertTrue($this->catalogue->covers($kind, $group, 'Natural Lead Climbing', 'Natural Top Rope'));
        $this->assertTrue($this->catalogue->covers($kind, $group, 'Natural Lead Climbing', 'Artificial Lead Climbing'));
        $this->assertTrue($this->catalogue->covers($kind, $group, 'Natural Lead Climbing', 'Artificial Top Rope'));
        $this->assertTrue($this->catalogue->covers($kind, $group, 'Multi Pitch', 'Natural Lead Climbing'));
        $this->assertTrue($this->catalogue->covers($kind, $group, 'Multi Pitch', 'Artificial Top Rope'));

        $this->assertFalse($this->catalogue->covers($kind, $group, 'Natural Top Rope', 'Artificial Lead Climbing'));
        $this->assertFalse($this->catalogue->covers($kind, $group, 'Artificial Lead Climbing', 'Natural Top Rope'));
        $this->assertFalse($this->catalogue->covers($kind, $group, 'Artificial Top Rope', 'Natural Top Rope'));
        $this->assertFalse($this->catalogue->covers($kind, $group, 'Natural Lead Climbing', 'Multi Pitch'));
    }

    public function test_canoeing_categories_do_not_cover_the_same_name_in_another_group(): void
    {
        $this->assertTrue($this->catalogue->covers('scout_permit', 'Canoeing River', 'B2', 'B1'));
        $this->assertTrue($this->catalogue->covers('scout_permit', 'Canoeing Open Inland', 'B2', 'B1'));
        $this->assertFalse($this->catalogue->categoryExists('scout_permit', 'Canoeing River', 'Open Inland'));
        $this->assertSame(
            ['B1'],
            $this->catalogue->family('scout_permit')['groups']['Canoeing River']['categories']['B2']['covers'],
        );
    }

    public function test_archery_with_compound_bows_covers_without_and_rejects_personal(): void
    {
        $this->assertTrue($this->catalogue->covers('scout_permit', 'Archery', 'with Compound Bows', 'without Compound Bows'));
        $this->assertFalse($this->catalogue->covers('scout_permit', 'Archery', 'without Compound Bows', 'with Compound Bows'));
        $this->assertSame(
            ['leadership', 'supervisory'],
            $this->catalogue->typesAllowed('scout_permit', 'Archery', 'without Compound Bows'),
        );
        $this->assertSame(
            ['personal', 'leadership'],
            $this->catalogue->typesAllowed('scout_permit', 'Climbing and Abseiling', 'Multi Pitch'),
        );
        $this->assertSame(
            ['leadership'],
            $this->catalogue->typesAllowed('scout_permit', 'Keelboating', 'A'),
        );
    }

    public function test_mountain_training_covers_follow_the_catalogue(): void
    {
        $kind = 'mountain_training';

        $this->assertTrue($this->catalogue->covers($kind, null, 'Hill and Moorland Leader', 'Lowland Leader'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Mountain Leader', 'Lowland Leader'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Climbing Wall Instructor', 'Indoor Climbing Assistant'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Climbing Wall Instructor (Abseil)', 'Climbing Wall Instructor'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Rock Climbing Instructor', 'Climbing Wall Instructor (Abseil)'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Rock Climbing Instructor', 'Indoor Climbing Assistant'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Climbing Wall Development Instructor', 'Climbing Wall Instructor'));
        $this->assertFalse($this->catalogue->covers($kind, null, 'Climbing Wall Development Instructor', 'Climbing Wall Instructor (Abseil)'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Rock Climbing Development Instructor', 'Rock Climbing Instructor'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Rock Climbing Development Instructor', 'Climbing Wall Development Instructor'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Mountaineering and Climbing Instructor', 'Mountain Leader'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Winter Mountaineering and Climbing Instructor', 'Mountaineering and Climbing Instructor'));
        $this->assertFalse($this->catalogue->covers($kind, null, 'International Mountain Leader', 'Winter Mountain Leader'));
        $this->assertFalse($this->catalogue->covers($kind, null, 'Climbing Wall Instructor', 'Climbing Wall Instructor (Abseil)'));
    }

    public function test_category_options_stay_inside_the_chosen_group(): void
    {
        $river = $this->catalogue->categoryOptions('scout_permit', 'Canoeing River');

        $this->assertSame(['B1' => 'B1', 'B2' => 'B2', 'B3' => 'B3', 'A' => 'A'], $river);
        $this->assertArrayNotHasKey('Artificial Top Rope', $river);
        $this->assertArrayHasKey(
            'Artificial Top Rope',
            $this->catalogue->categoryOptions('scout_permit', 'Climbing and Abseiling'),
        );
    }

    public function test_a_supervisory_permit_covers_leadership_but_not_personal(): void
    {
        $this->assertTrue($this->catalogue->permitTypeCovers('supervisory', 'leadership'));
        $this->assertTrue($this->catalogue->permitTypeCovers('supervisory', 'supervisory'));
        $this->assertFalse($this->catalogue->permitTypeCovers('leadership', 'supervisory'));
        $this->assertFalse($this->catalogue->permitTypeCovers('supervisory', 'personal'));
        $this->assertFalse($this->catalogue->permitTypeCovers('leadership', 'personal'));
    }

    public function test_girlguiding_keeps_a_scheme_select_and_levels_sit_under_it(): void
    {
        $this->assertTrue($this->catalogue->hasGroupSelect('girlguiding'));
        $this->assertFalse($this->catalogue->hasGroupSelect('mountain_training'));
        $this->assertSame(['Climbing' => 'Climbing'], $this->catalogue->groupOptions('girlguiding'));
        $this->assertSame(
            ['1' => 'Level 1', '2' => 'Level 2'],
            $this->catalogue->categoryOptions('girlguiding', 'Climbing'),
        );
        $this->assertSame([], $this->catalogue->categoryOptions('girlguiding', null));
    }

    public function test_girlguiding_level_two_covers_level_one(): void
    {
        $this->assertTrue($this->catalogue->covers('girlguiding', 'Climbing', '2', '1'));
        $this->assertFalse($this->catalogue->covers('girlguiding', 'Climbing', '1', '2'));
        $this->assertTrue($this->catalogue->covers('girlguiding', 'Climbing', '1', '1'));
    }

    public function test_nsra_awards_use_sections_and_a_short_summary_label(): void
    {
        $this->assertFalse($this->catalogue->hasGroupSelect('nsra'));
        $this->assertSame(
            'National Small-bore Rifle Association',
            $this->catalogue->familyOptions()['nsra'],
        );
        $this->assertSame('NSRA', $this->catalogue->familySummaryLabel('nsra'));
        $this->assertSame('NSRA', $this->catalogue->familyOptions(short: true)['nsra']);

        $options = $this->catalogue->categoryOptions('nsra', null);
        $this->assertSame('Club Instructor', $options['Coaching and Instructing']['Club Instructor']);
        $this->assertSame('Range Conducting Officer', $options['Range Management']['Range Conducting Officer']);
        $this->assertArrayNotHasKey('Officiating', $options);
        $this->assertFalse($this->catalogue->categoryExists('nsra', 'NSRA', 'Class A Scorer'));

        $this->assertTrue($this->catalogue->covers('nsra', null, 'Club Coach', 'Club Instructor'));
        $this->assertTrue($this->catalogue->covers('nsra', null, 'National Coach', 'Club Instructor'));
        $this->assertFalse($this->catalogue->covers('nsra', null, 'Range Conducting Officer', 'Club Instructor'));
        $this->assertFalse($this->catalogue->covers('nsra', null, 'YPS Tutor', 'Club Instructor'));
    }

    public function test_archery_gb_instructor_and_coaching_stacks_stay_separate(): void
    {
        $kind = 'archery_gb_awards';

        $this->assertFalse($this->catalogue->hasGroupSelect($kind));
        $this->assertSame('Archery GB awards', $this->catalogue->familyOptions()[$kind]);
        $this->assertSame('Archery GB', $this->catalogue->familySummaryLabel($kind));

        $options = $this->catalogue->categoryOptions($kind, null);
        $this->assertSame('Instructor Award', $options['Instructor']['Instructor Award']);
        $this->assertSame('Session Coach', $options['Coaching']['Session Coach']);

        $this->assertTrue($this->catalogue->covers($kind, null, 'Instructor Plus', 'Instructor Award'));
        $this->assertTrue($this->catalogue->covers($kind, null, 'Senior Coach', 'Session Coach'));
        $this->assertFalse($this->catalogue->covers($kind, null, 'Instructor Plus', 'Young Instructor Award'));
        $this->assertFalse($this->catalogue->covers($kind, null, 'Session Coach', 'Instructor Award'));
    }
}
