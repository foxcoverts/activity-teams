<?php

namespace Tests\Feature;

use App\Enums\IssuedMerchandiseSource;
use App\Enums\MerchandiseAvailability;
use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\CreateIssuedMerchandise;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\ListIssuedMerchandises;
use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\CreateMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\ListMerchandiseItems;
use App\Models\IssuedMerchandise;
use App\Models\MerchandiseItem;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MerchandiseShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_feature_flag_persists_on_the_team(): void
    {
        $team = Team::factory()->create(['features' => []]);
        $this->assertFalse($team->featureEnabled(TeamFeature::Shop));

        $team->enableFeature(TeamFeature::Shop);
        $team->save();

        $this->assertTrue($team->fresh()->featureEnabled(TeamFeature::Shop));
        $this->assertTrue(Team::factory()->withShop()->create()->featureEnabled(TeamFeature::Shop));
    }

    public function test_merchandise_nav_is_hidden_when_the_shop_feature_is_off(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->get(MerchandiseItemResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->get(IssuedMerchandiseResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->assertFalse(MerchandiseItemResource::canAccess());
        $this->assertFalse(IssuedMerchandiseResource::canAccess());
    }

    public function test_managers_can_access_items_and_issued_when_shop_is_on(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertTrue(MerchandiseItemResource::canAccess());
        $this->assertTrue(IssuedMerchandiseResource::canAccess());

        Livewire::test(ListMerchandiseItems::class)->assertSuccessful();
        Livewire::test(ListIssuedMerchandises::class)->assertSuccessful();
    }

    public function test_non_manager_members_cannot_access_merchandise_staff_resources(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertFalse(MerchandiseItemResource::canAccess());
        $this->assertFalse(IssuedMerchandiseResource::canAccess());
        $this->get(MerchandiseItemResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->get(IssuedMerchandiseResource::getUrl('index', tenant: $team))->assertForbidden();
    }

    public function test_system_admin_can_manage_items_but_not_issued_without_managing_the_team(): void
    {
        $team = Team::factory()->withShop()->create();
        $admin = User::factory()->systemAdmin()->create();

        $this->assertFalse($admin->manages($team));

        $this->actingAs($admin);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertTrue(MerchandiseItemResource::canAccess());
        $this->assertFalse(IssuedMerchandiseResource::canAccess());

        Livewire::test(ListMerchandiseItems::class)->assertSuccessful();
        $this->get(IssuedMerchandiseResource::getUrl('index', tenant: $team))->assertForbidden();
    }

    public function test_manager_can_create_a_catalogue_item_with_variants(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(CreateMerchandiseItem::class)
            ->fillForm([
                'title' => 'Necker',
                'description' => 'Team necker',
                'availability' => MerchandiseAvailability::Available->value,
                'variants' => [
                    ['label' => 'One size'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MerchandiseItem::query()->where('title', 'Necker')->first();
        $this->assertNotNull($item);
        $this->assertSame($team->getKey(), $item->team_id);
        $this->assertSame(MerchandiseAvailability::Available, $item->availability);
        $this->assertCount(1, $item->variants);
        $this->assertSame('One size', $item->variants->first()->label);
    }

    public function test_mothballed_items_remain_visible_to_staff(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        MerchandiseItem::factory()->for($team)->mothballed()->create([
            'title' => 'Old polo',
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ListMerchandiseItems::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(MerchandiseItem::query()->where('title', 'Old polo')->get());
    }

    public function test_record_gift_is_disabled_when_the_team_has_no_variants(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ListIssuedMerchandises::class)
            ->assertSuccessful()
            ->assertActionDisabled('create');

        Livewire::test(CreateIssuedMerchandise::class)
            ->assertRedirect(IssuedMerchandiseResource::getUrl('index', tenant: $team));
    }

    public function test_sole_variant_is_selected_when_an_item_is_chosen(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->create(['title' => 'Necker']);
        $variant = $item->variants()->firstOrFail();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(CreateIssuedMerchandise::class)
            ->fillForm([
                'merchandise_item_id' => $item->getKey(),
            ])
            ->assertFormSet([
                'merchandise_item_variant_id' => $variant->getKey(),
            ]);
    }

    public function test_manager_can_record_a_gift_and_filter_issued_by_item(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->create(['title' => 'Necker']);
        $variant = $item->variants()->firstOrFail();
        $other = MerchandiseItem::factory()->for($team)->create(['title' => 'Polo']);

        IssuedMerchandise::factory()->forTeam($team)->forItem($other)->create([
            'user_id' => $member->getKey(),
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ListIssuedMerchandises::class)
            ->assertSuccessful()
            ->assertActionEnabled('create');

        Livewire::test(CreateIssuedMerchandise::class)
            ->fillForm([
                'user_id' => $member->getKey(),
                'merchandise_item_id' => $item->getKey(),
                'issued_on' => '2024-06-01',
                'note' => 'Historic necker',
            ])
            ->assertFormSet([
                'merchandise_item_variant_id' => $variant->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $issued = IssuedMerchandise::query()
            ->where('user_id', $member->getKey())
            ->where('merchandise_item_variant_id', $variant->getKey())
            ->first();

        $this->assertNotNull($issued);
        $this->assertSame($team->getKey(), $issued->team_id);
        $this->assertSame(IssuedMerchandiseSource::Gift, $issued->source);
        $this->assertSame('Historic necker', $issued->note);

        Livewire::test(ListIssuedMerchandises::class)
            ->assertSuccessful()
            ->filterTable('merchandise_item_id', $item->getKey())
            ->assertCanSeeTableRecords([$issued])
            ->assertCanNotSeeTableRecords(
                IssuedMerchandise::query()->where('merchandise_item_variant_id', $other->variants()->first()->getKey())->get(),
            );
    }

    public function test_issued_records_are_isolated_per_team(): void
    {
        $team = Team::factory()->withShop()->create();
        $otherTeam = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();

        $own = IssuedMerchandise::factory()->forTeam($team)->create([
            'user_id' => $member->getKey(),
        ]);
        $foreign = IssuedMerchandise::factory()->forTeam($otherTeam)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ListIssuedMerchandises::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_variant_from_another_team_cannot_be_issued(): void
    {
        $team = Team::factory()->withShop()->create();
        $otherTeam = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        MerchandiseItem::factory()->for($team)->create(['title' => 'Local']);
        $foreignItem = MerchandiseItem::factory()->for($otherTeam)->create(['title' => 'Foreign']);
        $foreignVariant = $foreignItem->variants()->firstOrFail();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(CreateIssuedMerchandise::class)
            ->fillForm([
                'user_id' => $member->getKey(),
                'merchandise_item_id' => $foreignItem->getKey(),
                'merchandise_item_variant_id' => $foreignVariant->getKey(),
                'issued_on' => '2024-06-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['merchandise_item_variant_id']);

        $this->assertDatabaseMissing('issued_merchandise', [
            'merchandise_item_variant_id' => $foreignVariant->getKey(),
            'team_id' => $team->getKey(),
        ]);
    }

    public function test_catalogue_item_requires_at_least_one_variant(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(CreateMerchandiseItem::class)
            ->fillForm([
                'title' => 'Necker',
                'availability' => MerchandiseAvailability::Available->value,
                'variants' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['variants' => 'You must have at least one variant.']);
    }
}
