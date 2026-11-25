<?php

namespace Tests\Feature\Admin;

use App\Enums\MembershipRole;
use App\Enums\TeamFeature;
use App\Filament\Admin\Resources\InboundMails\Pages\ListInboundMails;
use App\Filament\Admin\Resources\InboundMails\Pages\ViewInboundMail;
use App\Filament\Admin\Resources\Keys\KeyResource as AdminKeyResource;
use App\Filament\Admin\Resources\Teams\TeamResource as AdminTeamResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Booking;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboundMailResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_the_inbound_mail_list_shows_the_newest_message_first(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $older = InboundMail::factory()->create();
        $older->forceFill(['created_at' => now()->subDays(2)])->save();
        $newer = InboundMail::factory()->create();
        $newer->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($admin);

        Livewire::test(ListInboundMails::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->sortTable('created_at', 'asc')
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true);
    }

    public function test_the_view_links_to_a_key_the_admin_can_open(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $key = Key::factory()->create(['name' => 'Cupboard key']);
        $mail = InboundMail::factory()->create();
        $mail->resource()->associate($key);
        $mail->save();

        $this->actingAs($admin);

        $url = AdminKeyResource::getUrl('view', ['record' => $key], panel: 'admin');

        Livewire::test(ViewInboundMail::class, ['record' => $mail->getKey()])
            ->assertSuccessful()
            ->assertSeeText('Key · Cupboard key')
            ->assertSeeHtml('href="'.$url.'"');
    }

    public function test_the_view_links_to_a_team_the_admin_can_open(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create(['name' => 'Climbing']);
        $mail = InboundMail::factory()->create();
        $mail->resource()->associate($team);
        $mail->save();

        $this->actingAs($admin);

        $url = AdminTeamResource::getUrl('edit', ['record' => $team], panel: 'admin');

        Livewire::test(ViewInboundMail::class, ['record' => $mail->getKey()])
            ->assertSuccessful()
            ->assertSeeText('Team · Climbing')
            ->assertSeeHtml('href="'.$url.'"');
    }

    public function test_the_view_shows_a_booking_without_a_link_when_the_admin_cannot_open_it(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $booking = Booking::factory()->confirmed()->create([
            'activity_label' => 'Evening climb',
        ]);
        $mail = InboundMail::factory()->create();
        $mail->resource()->associate($booking);
        $mail->save();

        $this->actingAs($admin);

        $url = BookingResource::getUrl('view', [
            'record' => $booking,
        ], panel: 'teams', tenant: $booking->team);

        Livewire::test(ViewInboundMail::class, ['record' => $mail->getKey()])
            ->assertSuccessful()
            ->assertSeeText('Booking · Evening climb')
            ->assertDontSeeHtml('href="'.$url.'"');
    }

    public function test_the_view_links_to_a_booking_when_the_admin_manages_its_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $booking = Booking::factory()->confirmed()->create([
            'activity_label' => 'Evening climb',
        ]);
        $booking->team->grant($admin, MembershipRole::Manager);

        $mail = InboundMail::factory()->create();
        $mail->resource()->associate($booking);
        $mail->save();

        $this->actingAs($admin);

        $url = BookingResource::getUrl('view', [
            'record' => $booking,
        ], panel: 'teams', tenant: $booking->team);

        Livewire::test(ViewInboundMail::class, ['record' => $mail->getKey()])
            ->assertSuccessful()
            ->assertSeeText('Booking · Evening climb')
            ->assertSeeHtml('href="'.$url.'"');
    }

    public function test_the_view_links_to_a_task_when_the_admin_belongs_to_its_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();
        $team->enableFeature(TeamFeature::Tasks);
        $team->save();
        $team->grant($admin, MembershipRole::Member);
        $task = Task::factory()->forTeam($team)->create(['summary' => 'Restock chalk']);

        $mail = InboundMail::factory()->create();
        $mail->resource()->associate($task);
        $mail->save();

        $this->actingAs($admin);

        $url = TaskResource::getUrl('view', [
            'record' => $task,
        ], panel: 'teams', tenant: $team);

        Livewire::test(ViewInboundMail::class, ['record' => $mail->getKey()])
            ->assertSuccessful()
            ->assertSeeText('Task · Restock chalk')
            ->assertSeeHtml('href="'.$url.'"');
    }

    public function test_the_view_shows_the_message_body_and_a_trimmed_payload(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $bodyText = "Hello.\n\nSorry I can't attend, I will be in Verona.";
        $mail = InboundMail::factory()->create();
        $payload = json_decode($mail->body, true, 512, JSON_THROW_ON_ERROR);
        $payload['text'] = $bodyText;
        $payload['html'] = '<p>'.$bodyText.'</p>';
        $payload['raw'] = "Subject: {$mail->subject}\r\n\r\n{$bodyText}";
        $payload['headerLines'] = [['key' => 'from', 'line' => 'From: someone@example.test']];
        $mail->forceFill(['body' => json_encode($payload, JSON_THROW_ON_ERROR)])->save();

        $this->actingAs($admin);

        Livewire::test(ViewInboundMail::class, ['record' => $mail->getKey()])
            ->assertSuccessful()
            ->assertSeeText('Sorry I can\'t attend, I will be in Verona.')
            ->assertSeeHtml('Hello.<br />')
            ->assertSeeText('"text": "Hello.')
            ->assertDontSeeText('"raw":')
            ->assertDontSeeText('"headerLines":')
            ->assertDontSeeText('<p>Sorry I can\'t attend');
    }
}
