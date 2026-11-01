<?php

namespace Tests\Feature;

use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Member\Resources\Inbox\Pages\ListNotifications;
use App\Mail\LeadNeededMail;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\LeadNeededNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inbox_lists_a_lead_needed_sentence_and_opening_it_marks_it_read(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $row = $manager->notifications()->first();

        $this->assertStringContainsString($booking->activity->name, $row->data['body']);
        $this->assertStringNotContainsString('Hello', $row->data['body']);
        $this->assertStringContainsString((string) $booking->getKey(), (string) $row->data['url']);

        $mail = (new LeadNeededMail($booking, null))->render();
        $this->assertStringContainsString($booking->activity->name, $mail);
        $this->assertNotSame($row->data['body'], trim(strip_tags($mail)));

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        $this->assertSame(1, InboxResource::unreadCount());

        Livewire::test(ListNotifications::class)
            ->assertSee($row->data['body'])
            ->assertSeeHtml('fi-font-bold')
            ->assertCanSeeTableRecords([$row])
            ->assertSet('tableRecordsPerPage', 25);

        $this->assertNull($row->fresh()->read_at);

        $manager->notify(new LeadNeededNotification($booking, null));
        $second = $manager->notifications()->whereNull('read_at')->first();
        $link = InboxResource::getUrl('view', ['record' => $second], panel: 'member');

        Livewire::test(ListNotifications::class)
            ->assertSee($second->data['body'])
            ->assertSeeHtml('href="'.e($link).'"');

        $this->assertNull($second->fresh()->read_at);

        $this->get($link)
            ->assertRedirect($second->data['url']);

        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNotNull($second->fresh()->digest_notified_at);
    }

    public function test_the_top_bar_shows_an_inbox_envelope_beside_the_user_menu(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($manager);

        Livewire::test(Topbar::class)
            ->assertSeeHtml('href="'.e(InboxResource::getUrl(panel: 'member')).'"')
            ->assertSeeHtml('aria-label="'.e('1 unread notification').'"')
            ->assertSee('1');

        $manager->unreadNotifications()->first()->markReadForDelivery();

        Livewire::test(Topbar::class)
            ->assertSeeHtml('href="'.e(InboxResource::getUrl(panel: 'member')).'"')
            ->assertSeeHtml('aria-label="'.e('Inbox').'"')
            ->assertDontSeeHtml('>1<');
    }

    public function test_the_inbox_when_column_shows_how_long_ago_with_a_datetime_tooltip(): void
    {
        $this->travelTo('2026-11-01 17:32:00');

        $manager = User::factory()->teamManager()->create(['timezone' => 'Europe/London']);
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $row = $manager->notifications()->first();
        $row->forceFill(['created_at' => '2026-11-01 15:32:00'])->save();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(ListNotifications::class)
            ->assertSee('2 hours ago')
            ->assertSee('1 Nov 2026, 15:32');
    }

    public function test_sorting_by_when_puts_the_newest_notification_first(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);

        $manager->notify(new LeadNeededNotification($booking, null));
        $older = $manager->notifications()->first();
        $older->forceFill(['created_at' => now()->subDay()])->save();

        $manager->notify(new LeadNeededNotification($booking, null));
        $newer = $manager->notifications()->whereKeyNot($older->getKey())->first();
        $newer->markReadForDelivery();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(ListNotifications::class)
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
            ->assertSeeHtml('fi-font-bold')
            ->assertSeeHtml('fi-font-normal')
            ->assertSeeHtml('bg-gray-100')
            ->sortTable('created_at', 'desc')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_filtering_shows_only_unread_or_only_read_notifications(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $unread = $manager->notifications()->first();
        $manager->notify(new LeadNeededNotification($booking, null));
        $read = $manager->notifications()->whereKeyNot($unread->getKey())->first();
        $read->markReadForDelivery();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(ListNotifications::class)
            ->assertCanSeeTableRecords([$unread, $read])
            ->assertSee('Mark all read')
            ->assertSeeHtml('actions.markAllRead')
            ->filterTable('read_state', 'unread')
            ->assertCanSeeTableRecords([$unread])
            ->assertCanNotSeeTableRecords([$read])
            ->assertActionVisible(TestAction::make('markAllRead')->table())
            ->filterTable('read_state', 'read')
            ->assertCanSeeTableRecords([$read])
            ->assertCanNotSeeTableRecords([$unread])
            ->assertActionHidden(TestAction::make('markAllRead')->table())
            ->assertDontSee('Mark all read')
            ->resetTableFilters()
            ->assertActionVisible(TestAction::make('markAllRead')->table())
            ->assertSee('Mark all read');
    }

    public function test_marking_selected_notifications_read_leaves_the_others_unread(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $manager->notify(new LeadNeededNotification($booking, null));
        $selected = $manager->notifications()->oldest()->first();
        $other = $manager->notifications()->whereKeyNot($selected->getKey())->first();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(ListNotifications::class)
            ->selectTableRecords([$selected])
            ->callAction(TestAction::make('markSelectedRead')->table()->bulk());

        $this->assertNotNull($selected->fresh()->read_at);
        $this->assertNotNull($selected->fresh()->digest_notified_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_deleting_selected_notifications_removes_only_those_rows(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $manager->notify(new LeadNeededNotification($booking, null));
        $selected = $manager->notifications()->oldest()->first();
        $other = $manager->notifications()->whereKeyNot($selected->getKey())->first();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(ListNotifications::class)
            ->selectTableRecords([$selected])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertModelMissing($selected);
        $this->assertModelExists($other);
    }

    public function test_mark_all_read_marks_every_notification_when_none_are_selected(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $manager->notify(new LeadNeededNotification($booking, null));
        $rows = $manager->notifications()->get();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(ListNotifications::class)
            ->callAction(TestAction::make('markAllRead')->table())
            ->assertActionHidden(TestAction::make('markAllRead')->table());

        foreach ($rows as $row) {
            $this->assertNotNull($row->fresh()->read_at);
            $this->assertNotNull($row->fresh()->digest_notified_at);
        }
    }

    public function test_another_person_does_not_see_the_inbox_row(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $row = $manager->notifications()->first();
        $other = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($other);

        Livewire::test(ListNotifications::class)
            ->assertCanNotSeeTableRecords([$row]);
    }

    public function test_another_person_cannot_open_an_inbox_notification(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'team_id' => $manager->managedTeams()->first()->getKey(),
        ]);
        $manager->notify(new LeadNeededNotification($booking, null));
        $row = $manager->notifications()->first();
        $other = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($other);

        $this->get(InboxResource::getUrl('view', ['record' => $row], panel: 'member'))
            ->assertNotFound();

        $this->assertNull($row->fresh()->read_at);
    }
}
