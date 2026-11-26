<?php

namespace Tests\Feature;

use App\Enums\IssuedMerchandiseSource;
use App\Enums\MerchandiseAvailability;
use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\CreateIssuedMerchandise;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\ListIssuedMerchandises;
use App\Filament\Teams\Resources\MerchandiseBasketLines\MerchandiseBasketLineResource;
use App\Filament\Teams\Resources\MerchandiseBasketLines\Pages\ListMerchandiseBasketLines;
use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\CreateMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\EditMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\ListMerchandiseItems;
use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ListMerchandiseOrders;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ViewMerchandiseOrder;
use App\Filament\Teams\Resources\MerchandiseOrders\RelationManagers\ItemsRelationManager;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Filament\Teams\Resources\MerchandiseShops\Pages\ListMerchandiseShops;
use App\Filament\Teams\Resources\MerchandiseShops\Pages\ViewMerchandiseShop;
use App\Models\IssuedMerchandise;
use App\Models\MerchandiseBasketLine;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemImage;
use App\Models\MerchandiseItemOptionAxis;
use App\Models\MerchandiseItemOptionValue;
use App\Models\MerchandiseItemVariant;
use App\Models\MerchandiseOrder;
use App\Models\Team;
use App\Models\User;
use App\Notifications\MerchandiseOrderSubmittedNotification;
use App\Support\MerchandiseVariantOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'availability' => MerchandiseAvailability::Draft->value,
                'variants' => [
                    [
                        'label' => 'One size',
                        'price' => '0.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MerchandiseItem::query()->where('title', 'Necker')->first();
        $this->assertNotNull($item);
        $this->assertSame($team->getKey(), $item->team_id);
        $this->assertSame(MerchandiseAvailability::Draft, $item->availability);
        $this->assertCount(1, $item->variants);
        $this->assertSame('One size', $item->variants->first()->label);
        $this->assertSame('0.00', $item->variants->first()->price);
        $this->assertSame(MerchandiseAvailability::Draft, $item->variants->first()->availability);
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
                'availability' => MerchandiseAvailability::Draft->value,
                'variants' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['variants' => 'You must have at least one variant.']);
    }

    public function test_available_item_requires_an_available_variant_and_image(): void
    {
        Storage::fake('public');

        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->draft()->create([
            'title' => 'Polo',
        ]);
        $variant = $item->variants()->firstOrFail();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'availability' => MerchandiseAvailability::Available->value,
                'variants' => [
                    [
                        'id' => $variant->getKey(),
                        'label' => 'M',
                        'price' => '15.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['availability']);

        Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'availability' => MerchandiseAvailability::Available->value,
                'variants' => [
                    [
                        'id' => $variant->getKey(),
                        'label' => 'M',
                        'price' => '15.00',
                        'availability' => MerchandiseAvailability::Available->value,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['availability' => 'Available items need at least one image.']);

        $path = 'merchandise/'.$team->getKey().'/polo.jpg';
        Storage::disk('public')->put($path, $this->jpegBinary(800, 600));
        MerchandiseItemImage::factory()->for($item, 'item')->create([
            'path' => $path,
            'sort' => 0,
        ]);

        Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'availability' => MerchandiseAvailability::Available->value,
                'variants' => [
                    [
                        'id' => $variant->getKey(),
                        'label' => 'M',
                        'price' => '15.00',
                        'availability' => MerchandiseAvailability::Available->value,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame(MerchandiseAvailability::Available, $item->availability);
        $this->assertCount(1, $item->images);
        $this->assertSame('15.00', $item->variants->first()->price);
        $this->assertSame(MerchandiseAvailability::Available, $item->variants->first()->availability);
    }

    public function test_mothballed_item_cannot_have_only_draft_variants(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(CreateMerchandiseItem::class)
            ->fillForm([
                'title' => 'Old kit',
                'availability' => MerchandiseAvailability::Mothballed->value,
                'variants' => [
                    [
                        'label' => 'M',
                        'price' => '10.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['availability']);
    }

    public function test_variant_with_issued_rows_cannot_be_deleted(): void
    {
        $team = Team::factory()->withShop()->create();
        $item = MerchandiseItem::factory()->for($team)->create();
        $variant = $item->variants()->firstOrFail();
        IssuedMerchandise::factory()->forTeam($team)->forVariant($variant)->create();

        $this->expectException(ValidationException::class);

        $variant->delete();
    }

    public function test_members_can_browse_available_shop_products_only(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();

        $available = MerchandiseItem::factory()->for($team)->withImage()->create([
            'title' => 'Shop polo',
            'availability' => MerchandiseAvailability::Available,
        ]);
        $available->variants()->firstOrFail()->update([
            'price' => '12.50',
            'availability' => MerchandiseAvailability::Available,
        ]);

        MerchandiseItem::factory()->for($team)->draft()->create(['title' => 'Draft hoodie']);
        MerchandiseItem::factory()->for($team)->mothballed()->create(['title' => 'Old necker']);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertTrue(MerchandiseShopResource::canAccess());
        $this->assertFalse(MerchandiseItemResource::canAccess());

        Livewire::test(ListMerchandiseShops::class)
            ->assertSuccessful()
            ->assertSee('Shop polo')
            ->assertSee('£12.50')
            ->assertDontSee('Draft hoodie')
            ->assertDontSee('Old necker');

        Livewire::test(ViewMerchandiseShop::class, ['record' => $available->getKey()])
            ->assertSuccessful()
            ->assertSee('Shop polo')
            ->assertSee('One size')
            ->assertSee('£12.50')
            ->assertSee(__('View larger image'))
            ->assertDontSee('Gallery');
    }

    public function test_shop_hides_draft_and_mothballed_variants_on_product_detail(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create([
            'title' => 'Hoody',
            'description' => 'Warm team hoody',
            'availability' => MerchandiseAvailability::Available,
        ]);
        $item->variants()->delete();
        MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M',
            'price' => '20.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 0,
        ]);
        MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'L',
            'price' => '22.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 1,
        ]);
        MerchandiseItemVariant::factory()->for($item, 'item')->draft()->create([
            'label' => 'XL draft',
            'price' => '20.00',
            'sort' => 2,
        ]);
        MerchandiseItemVariant::factory()->for($item, 'item')->mothballed()->create([
            'label' => 'S mothballed',
            'price' => '20.00',
            'sort' => 3,
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->assertSuccessful()
            ->assertSee('Warm team hoody')
            ->assertSee('M - £20.00')
            ->assertSee('L - £22.00')
            ->assertDontSee('XL draft')
            ->assertDontSee('S mothballed')
            ->assertDontSee('Gallery');
    }

    public function test_non_member_system_admin_cannot_access_shop(): void
    {
        $team = Team::factory()->withShop()->create();
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertTrue(MerchandiseItemResource::canAccess());
        $this->assertFalse(MerchandiseShopResource::canAccess());
        $this->get(MerchandiseShopResource::getUrl('index', tenant: $team))->assertForbidden();
    }

    public function test_shop_is_forbidden_when_feature_is_off(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->teamMember($team)->create();

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertFalse(MerchandiseShopResource::canAccess());
        $this->get(MerchandiseShopResource::getUrl('index', tenant: $team))->assertForbidden();
    }

    public function test_manager_can_define_personalisation_fields_on_an_item(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->draft()->create(['title' => 'Polo']);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'availability' => MerchandiseAvailability::Draft->value,
                'personalisation_fields' => [
                    [
                        'key' => (string) Str::ulid(),
                        'label' => 'Name on shirt',
                        'required' => true,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertCount(1, $item->personalisationFieldDefinitions());
        $this->assertSame('Name on shirt', $item->personalisationFieldDefinitions()[0]['label']);
        $this->assertTrue($item->personalisationFieldDefinitions()[0]['required']);
    }

    public function test_member_can_add_to_basket_submit_and_cancel_an_order(): void
    {
        Notification::fake();

        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $fieldKey = (string) Str::ulid();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create([
            'title' => 'Named polo',
            'personalisation_fields' => [
                ['key' => $fieldKey, 'label' => 'Name', 'required' => true],
            ],
        ]);
        $variant = $item->variants()->firstOrFail();
        $variant->update(['price' => '15.00', 'label' => 'M']);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->set('selectedVariantId', $variant->getKey())
            ->set('quantity', 2)
            ->set('personalisation', [$fieldKey => 'Alex'])
            ->call('addToBasket')
            ->assertHasNoErrors()
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $line = MerchandiseBasketLine::query()->forTeamUser($team, $member)->first();
        $this->assertNotNull($line);
        $this->assertSame(2, $line->quantity);
        $this->assertSame('Alex', $line->personalisationSnapshot()[0]['value']);
        $this->assertSame(2, MerchandiseBasketLineResource::quantityInBasket($team, $member));
        $this->assertSame('2', MerchandiseBasketLineResource::getNavigationBadge());

        $productUrl = MerchandiseShopResource::getUrl('view', ['record' => $item]);

        Livewire::test(ListMerchandiseBasketLines::class)
            ->assertSuccessful()
            ->assertSee('£30.00')
            ->assertSeeHtml($productUrl)
            ->assertTableColumnSummarySet('line_total', 'total', 30)
            ->assertActionExists('continueShopping')
            ->assertActionExists('removeAllItems')
            ->assertCanSeeTableRecords([$line])
            ->callAction('submitOrder')
            ->assertRedirect();

        $order = MerchandiseOrder::query()->where('user_id', $member->getKey())->first();
        $this->assertNotNull($order);
        $this->assertSame(0, MerchandiseBasketLine::query()->forTeamUser($team, $member)->count());
        $this->assertCount(1, $order->lines);
        $this->assertSame('15.00', $order->lines->first()->unit_price);
        $this->assertSame('Alex', $order->lines->first()->personalisationSnapshot()[0]['value']);

        Notification::assertSentTo($manager, MerchandiseOrderSubmittedNotification::class);
        Notification::assertNotSentTo($member, MerchandiseOrderSubmittedNotification::class);

        $this->assertTrue(MerchandiseOrderResource::canAccess());
        $this->assertTrue(MerchandiseBasketLineResource::canAccess());

        Livewire::test(ListMerchandiseOrders::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$order]);

        Livewire::test(ViewMerchandiseOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful()
            ->assertSee('Pending approval')
            ->assertSee('Waiting for a team manager to review this order.')
            ->assertSee('Items')
            ->assertSee('Named polo')
            ->assertSee('Alex')
            ->assertSeeLivewire(ItemsRelationManager::class)
            ->callAction('cancel')
            ->assertHasNoErrors();

        Livewire::test(ViewMerchandiseOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful()
            ->assertSee('Cancelled')
            ->assertSee('This order was cancelled and will not be fulfilled.');

        $this->assertTrue($order->fresh()->status->value === 'cancelled');
    }

    public function test_manager_orders_list_defaults_member_filter_to_me(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();

        $mine = MerchandiseOrder::factory()->for($team)->for($manager)->withLine()->create([
            'submitted_at' => now()->subHour(),
        ]);
        $theirs = MerchandiseOrder::factory()->for($team)->for($member)->withLine()->create([
            'submitted_at' => now()->subMinutes(30),
        ]);
        $cancelled = MerchandiseOrder::factory()->for($team)->for($member)->cancelled()->withLine()->create([
            'submitted_at' => now()->subMinutes(15),
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ListMerchandiseOrders::class)
            ->assertSuccessful()
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('user_id')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $cancelled])
            ->assertTableColumnExists('user.name')
            ->filterTable('user_id')
            ->assertCanSeeTableRecords([$mine, $theirs, $cancelled])
            ->filterTable('user_id', $member->getKey())
            ->assertCanSeeTableRecords([$theirs, $cancelled])
            ->assertCanNotSeeTableRecords([$mine])
            ->filterTable('status', 'cancelled')
            ->assertCanSeeTableRecords([$cancelled])
            ->assertCanNotSeeTableRecords([$mine, $theirs]);
    }

    public function test_required_personalisation_blocks_add_to_basket(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $fieldKey = (string) Str::ulid();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create([
            'personalisation_fields' => [
                ['key' => $fieldKey, 'label' => 'Name', 'required' => true],
            ],
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->set('personalisation', [$fieldKey => ''])
            ->call('addToBasket');

        $this->assertSame(0, MerchandiseBasketLine::query()->forTeamUser($team, $member)->count());
    }

    public function test_basket_quantity_zero_and_remove_all_clear_lines(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create();
        $line = MerchandiseBasketLine::factory()->forTeamUser($team, $member, $item)->create([
            'quantity' => 2,
        ]);
        $other = MerchandiseBasketLine::factory()->forTeamUser($team, $member)->create([
            'quantity' => 1,
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ListMerchandiseBasketLines::class)
            ->assertSuccessful()
            ->call('updateTableColumnState', 'quantity', $line->getKey(), 0)
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $this->assertDatabaseMissing('merchandise_basket_lines', ['id' => $line->getKey()]);

        Livewire::test(ListMerchandiseBasketLines::class)
            ->callAction('removeAllItems')
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $this->assertDatabaseMissing('merchandise_basket_lines', ['id' => $other->getKey()]);
        $this->assertSame(0, MerchandiseBasketLine::query()->forTeamUser($team, $member)->count());
    }

    public function test_manager_can_save_item_with_two_option_axes_and_composed_labels(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->draft()->create(['title' => 'Hoody']);
        $existing = $item->variants()->firstOrFail();

        $size = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Size',
            'sort' => 0,
        ]);
        $sizeM = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create([
            'label' => 'M',
            'sort' => 0,
        ]);
        $sizeL = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create([
            'label' => 'L',
            'sort' => 1,
        ]);
        $colour = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Colour',
            'sort' => 1,
        ]);
        $black = MerchandiseItemOptionValue::factory()->for($colour, 'axis')->create([
            'label' => 'Black',
            'sort' => 0,
        ]);
        $orange = MerchandiseItemOptionValue::factory()->for($colour, 'axis')->create([
            'label' => 'Orange',
            'sort' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $component = Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'title' => 'Hoody',
                'availability' => MerchandiseAvailability::Draft->value,
                'optionAxes' => [
                    [
                        'id' => $size->getKey(),
                        'name' => 'Size',
                        'values' => [
                            ['id' => $sizeM->getKey(), 'label' => 'M'],
                            ['id' => $sizeL->getKey(), 'label' => 'L'],
                        ],
                    ],
                    [
                        'id' => $colour->getKey(),
                        'name' => 'Colour',
                        'values' => [
                            ['id' => $black->getKey(), 'label' => 'Black'],
                            ['id' => $orange->getKey(), 'label' => 'Orange'],
                        ],
                    ],
                ],
                'variants' => [
                    [
                        'id' => $existing->getKey(),
                        'price' => '20.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                        'option_value_ids' => [
                            $size->getKey() => $sizeM->getKey(),
                            $colour->getKey() => $black->getKey(),
                        ],
                    ],
                    [
                        'price' => '22.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                        'option_value_ids' => [
                            $size->getKey() => $sizeL->getKey(),
                            $colour->getKey() => $orange->getKey(),
                        ],
                    ],
                ],
            ]);

        $variantState = collect($component->get('data.variants'))->values();
        $this->assertSame(
            [$sizeM->getKey(), $black->getKey()],
            [
                data_get($variantState->get(0), 'option_value_ids.'.$size->getKey()),
                data_get($variantState->get(0), 'option_value_ids.'.$colour->getKey()),
            ],
            'Form state missing option selections before save: '.json_encode($variantState->get(0)),
        );

        $component
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh()->load(['optionAxes.values', 'variants.optionValues']);

        $this->assertTrue($item->hasOptionAxes());
        $this->assertCount(2, $item->optionAxes);
        $this->assertCount(2, $item->variants);

        $mBlack = $item->variants->firstWhere('label', 'M · Black');
        $this->assertNotNull($mBlack);
        $this->assertEqualsCanonicalizing(
            ['M', 'Black'],
            $mBlack->optionValues->pluck('label')->all(),
        );

        $lOrange = $item->variants->firstWhere('label', 'L · Orange');
        $this->assertNotNull($lOrange);
        $this->assertEqualsCanonicalizing(
            ['L', 'Orange'],
            $lOrange->optionValues->pluck('label')->all(),
        );
    }

    public function test_duplicate_option_combinations_are_rejected(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->draft()->create(['title' => 'Hoody']);
        $existing = $item->variants()->firstOrFail();

        $size = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Size',
            'sort' => 0,
        ]);
        $sizeS = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create([
            'label' => 'S',
            'sort' => 0,
        ]);
        $sizeM = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create([
            'label' => 'M',
            'sort' => 1,
        ]);
        $colour = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Colour',
            'sort' => 1,
        ]);
        $black = MerchandiseItemOptionValue::factory()->for($colour, 'axis')->create([
            'label' => 'Black',
            'sort' => 0,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'title' => 'Hoody',
                'availability' => MerchandiseAvailability::Draft->value,
                'optionAxes' => [
                    [
                        'id' => $size->getKey(),
                        'name' => 'Size',
                        'values' => [
                            ['id' => $sizeS->getKey(), 'label' => 'S'],
                            ['id' => $sizeM->getKey(), 'label' => 'M'],
                        ],
                    ],
                    [
                        'id' => $colour->getKey(),
                        'name' => 'Colour',
                        'values' => [
                            ['id' => $black->getKey(), 'label' => 'Black'],
                        ],
                    ],
                ],
                'variants' => [
                    [
                        'id' => $existing->getKey(),
                        'price' => '20.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                        'option_value_ids' => [
                            $size->getKey() => $sizeS->getKey(),
                            $colour->getKey() => $black->getKey(),
                        ],
                    ],
                    [
                        'price' => '20.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                        'option_value_ids' => [
                            $size->getKey() => $sizeS->getKey(),
                            $colour->getKey() => $black->getKey(),
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['variants' => 'Each variant must use a unique combination of options.']);
    }

    public function test_shop_cascading_options_resolve_sparse_variants_and_add_to_basket(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create(['title' => 'Hoody']);
        $item->variants()->delete();

        $size = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Size',
            'sort' => 0,
        ]);
        $sizeM = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create(['label' => 'M', 'sort' => 0]);
        $sizeL = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create(['label' => 'L', 'sort' => 1]);
        $colour = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Colour',
            'sort' => 1,
        ]);
        $black = MerchandiseItemOptionValue::factory()->for($colour, 'axis')->create(['label' => 'Black', 'sort' => 0]);
        $orange = MerchandiseItemOptionValue::factory()->for($colour, 'axis')->create(['label' => 'Orange', 'sort' => 1]);

        $mBlack = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M · Black',
            'price' => '20.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 0,
        ]);
        $mBlack->optionValues()->sync([$sizeM->getKey(), $black->getKey()]);

        $mOrange = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M · Orange',
            'price' => '21.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 1,
        ]);
        $mOrange->optionValues()->sync([$sizeM->getKey(), $orange->getKey()]);

        $lBlack = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'L · Black',
            'price' => '22.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 2,
        ]);
        $lBlack->optionValues()->sync([$sizeL->getKey(), $black->getKey()]);

        $item->refresh()->load(['optionAxes.values', 'variants.optionValues']);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $sizeKey = (string) $size->getKey();
        $colourKey = (string) $colour->getKey();

        $component = Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->assertSuccessful()
            ->assertSee('Size')
            ->assertSee('Colour')
            ->assertDontSeeHtml('id="shop-variant"')
            ->assertSet('selectedVariantId', '');

        $this->assertEqualsCanonicalizing(
            [$sizeM->getKey(), $sizeL->getKey()],
            $component->instance()->availableValuesForAxis($size)->pluck('id')->all(),
        );

        $component
            ->set('selectedOptionValueIds.'.$sizeKey, $sizeM->getKey())
            ->assertSet('selectedVariantId', '');

        $this->assertEqualsCanonicalizing(
            [$black->getKey(), $orange->getKey()],
            $component->instance()->availableValuesForAxis($colour)->pluck('id')->all(),
        );

        $component
            ->set('selectedOptionValueIds.'.$colourKey, $orange->getKey())
            ->assertSet('selectedVariantId', $mOrange->getKey())
            ->assertSee('£21.00');

        $component
            ->set('selectedOptionValueIds.'.$sizeKey, $sizeL->getKey())
            ->assertSet('selectedOptionValueIds.'.$colourKey, $black->getKey())
            ->assertSet('selectedVariantId', $lBlack->getKey())
            ->set('quantity', 1)
            ->call('addToBasket')
            ->assertHasNoErrors();

        $line = MerchandiseBasketLine::query()->forTeamUser($team, $member)->first();
        $this->assertNotNull($line);
        $this->assertSame($lBlack->getKey(), $line->merchandise_item_variant_id);
    }

    public function test_shop_auto_selects_sole_option_values(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create(['title' => 'Hoody']);
        $item->variants()->delete();

        $size = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Size',
            'sort' => 0,
        ]);
        $sizeM = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create(['label' => 'M', 'sort' => 0]);
        $sizeL = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create(['label' => 'L', 'sort' => 1]);
        $colour = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Colour',
            'sort' => 1,
        ]);
        $black = MerchandiseItemOptionValue::factory()->for($colour, 'axis')->create(['label' => 'Black', 'sort' => 0]);

        $mBlack = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M · Black',
            'price' => '20.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 0,
        ]);
        $mBlack->optionValues()->sync([$sizeM->getKey(), $black->getKey()]);

        $lBlack = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'L · Black',
            'price' => '22.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 1,
        ]);
        $lBlack->optionValues()->sync([$sizeL->getKey(), $black->getKey()]);

        $item->refresh()->load(['optionAxes.values', 'variants.optionValues']);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $sizeKey = (string) $size->getKey();
        $colourKey = (string) $colour->getKey();

        Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->assertSuccessful()
            ->assertSet('selectedOptionValueIds.'.$colourKey, $black->getKey())
            ->assertSet('selectedVariantId', '')
            ->assertSee('From')
            ->assertSee('£20.00')
            ->assertDontSee('M · Black')
            ->set('selectedOptionValueIds.'.$sizeKey, $sizeM->getKey())
            ->assertSet('selectedOptionValueIds.'.$colourKey, $black->getKey())
            ->assertSet('selectedVariantId', $mBlack->getKey())
            ->assertSee('M · Black')
            ->assertDontSee('From');
    }

    public function test_zero_axis_items_still_use_flat_variant_select(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create(['title' => 'Necker']);
        $item->variants()->firstOrFail()->update([
            'label' => 'One size',
            'price' => '5.00',
            'availability' => MerchandiseAvailability::Available,
        ]);
        MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'Youth',
            'price' => '4.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 1,
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->assertSuccessful()
            ->assertSeeHtml('id="shop-variant"')
            ->assertSee('One size - £5.00')
            ->assertSee('Youth - £4.00');

        $this->assertFalse($item->fresh()->hasOptionAxes());
        $this->assertSame([], MerchandiseVariantOptions::combinationValueIdSets(collect()));
    }

    private function jpegBinary(int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($canvas);
        $colour = imagecolorallocate($canvas, 40, 120, 200);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $colour);

        ob_start();
        imagejpeg($canvas, null, 90);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $binary;
    }
}
