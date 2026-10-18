<?php

namespace Tests\Unit;

use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;
use PHPUnit\Framework\TestCase;

class BookingAttendeeStatusTest extends TestCase
{
    public function test_status_colors_and_icons_match_the_roster_badges(): void
    {
        $this->assertSame(Palette::positive(), BookingAttendeeStatus::Accepted->getColor());
        $this->assertSame(Heroicon::CheckCircle, BookingAttendeeStatus::Accepted->getIcon());

        $this->assertSame(Palette::uncertain(), BookingAttendeeStatus::Tentative->getColor());
        $this->assertSame(Heroicon::CheckCircle, BookingAttendeeStatus::Tentative->getIcon());

        $this->assertSame(Palette::destructive(), BookingAttendeeStatus::Declined->getColor());
        $this->assertSame(Heroicon::XCircle, BookingAttendeeStatus::Declined->getIcon());

        $this->assertSame(Palette::neutral(), BookingAttendeeStatus::NeedsAction->getColor());
        $this->assertSame(Heroicon::QuestionMarkCircle, BookingAttendeeStatus::NeedsAction->getIcon());
    }

    public function test_a_response_maps_to_the_attendance_status(): void
    {
        $this->assertSame(BookingAttendeeStatus::Accepted, BookingAttendeeResponse::Yes->toStatus());
        $this->assertSame(BookingAttendeeStatus::Declined, BookingAttendeeResponse::No->toStatus());
        $this->assertSame(BookingAttendeeStatus::Tentative, BookingAttendeeResponse::Maybe->toStatus());

        $this->assertSame(BookingAttendeeResponse::Yes, BookingAttendeeResponse::tryFromStatus(BookingAttendeeStatus::Accepted));
        $this->assertSame(BookingAttendeeResponse::No, BookingAttendeeResponse::tryFromStatus(BookingAttendeeStatus::Declined));
        $this->assertSame(BookingAttendeeResponse::Maybe, BookingAttendeeResponse::tryFromStatus(BookingAttendeeStatus::Tentative));
        $this->assertNull(BookingAttendeeResponse::tryFromStatus(BookingAttendeeStatus::NeedsAction));

        $this->assertSame(Palette::uncertain(), BookingAttendeeResponse::Maybe->getColor());
        $this->assertSame(Heroicon::QuestionMarkCircle, BookingAttendeeResponse::Maybe->getIcon());
    }
}
