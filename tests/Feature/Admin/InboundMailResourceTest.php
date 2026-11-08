<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\InboundMails\Pages\ListInboundMails;
use App\Models\InboundMail;
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
}
