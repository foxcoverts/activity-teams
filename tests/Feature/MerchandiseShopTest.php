<?php

namespace Tests\Feature;

use App\Actions\ApproveMerchandiseOrderAction;
use App\Actions\EnsureMerchandiseSupplierOrderDraftAction;
use App\Actions\RejectMerchandiseOrderAction;
use App\Actions\SendMerchandiseSupplierOrderAction;
use App\Actions\SetMerchandiseOrderDiscountAction;
use App\Actions\SetMerchandiseOrderLinePriceAction;
use App\Actions\SyncMerchandiseSupplierOrderDraftLinesAction;
use App\Actions\ToggleMerchandiseOrderLineArrivedAction;
use App\Enums\IssuedMerchandiseSource;
use App\Enums\MerchandiseAvailability;
use App\Enums\MerchandiseOrderPaymentStatus;
use App\Enums\MerchandiseOrderStatus;
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
use App\Filament\Teams\Resources\MerchandiseItems\Pages\ViewMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ListMerchandiseOrders;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ViewMerchandiseOrder;
use App\Filament\Teams\Resources\MerchandiseOrders\RelationManagers\ItemsRelationManager;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Filament\Teams\Resources\MerchandiseShops\Pages\ListMerchandiseShops;
use App\Filament\Teams\Resources\MerchandiseShops\Pages\ViewMerchandiseShop;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\MerchandiseSupplierOrderResource;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\Pages\ManageMerchandiseSupplierOrders;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\Pages\ViewMerchandiseSupplierOrder;
use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\CreateMerchandiseSupplier;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\EditMerchandiseSupplier;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\ListMerchandiseSuppliers;
use App\Mail\MerchandiseOrderTreasurerRequestMail;
use App\Models\IssuedMerchandise;
use App\Models\MerchandiseBasketLine;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemImage;
use App\Models\MerchandiseItemOptionAxis;
use App\Models\MerchandiseItemOptionValue;
use App\Models\MerchandiseItemVariant;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use App\Models\MerchandiseSupplier;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use App\Models\User;
use App\Notifications\MerchandiseOrderApprovedNotification;
use App\Notifications\MerchandiseOrderRejectedNotification;
use App\Notifications\MerchandiseOrderSubmittedNotification;
use App\Support\MerchandiseSupplierOrderExporter;
use App\Support\MerchandiseVariantOptions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
        $this->get(MerchandiseSupplierResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->get(MerchandiseSupplierOrderResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->assertFalse(MerchandiseItemResource::canAccess());
        $this->assertFalse(IssuedMerchandiseResource::canAccess());
        $this->assertFalse(MerchandiseSupplierResource::canAccess());
        $this->assertFalse(MerchandiseSupplierOrderResource::canAccess());
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
        $this->assertTrue(MerchandiseSupplierResource::canAccess());

        Livewire::test(ListMerchandiseItems::class)->assertSuccessful();
        Livewire::test(ListIssuedMerchandises::class)->assertSuccessful();
        Livewire::test(ListMerchandiseSuppliers::class)->assertSuccessful();
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
        $this->assertFalse(MerchandiseSupplierResource::canAccess());
        $this->get(MerchandiseItemResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->get(IssuedMerchandiseResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->get(MerchandiseSupplierResource::getUrl('index', tenant: $team))->assertForbidden();
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
        $this->assertTrue(MerchandiseSupplierResource::canAccess());
        $this->assertFalse(IssuedMerchandiseResource::canAccess());

        Livewire::test(ListMerchandiseItems::class)->assertSuccessful();
        Livewire::test(ListMerchandiseSuppliers::class)->assertSuccessful();
        $this->get(IssuedMerchandiseResource::getUrl('index', tenant: $team))->assertForbidden();
    }

    public function test_manager_can_create_supplier_link_item_and_edit_stock(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(CreateMerchandiseSupplier::class)
            ->fillForm([
                'name' => 'Scout Store',
                'notes' => 'Order via the B2B portal.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $supplier = MerchandiseSupplier::query()->where('name', 'Scout Store')->first();
        $this->assertNotNull($supplier);
        $this->assertSame($team->getKey(), $supplier->team_id);
        $this->assertSame('Order via the B2B portal.', $supplier->notes);

        $item = MerchandiseItem::factory()->for($team)->draft()->create([
            'title' => 'Polo',
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);
        $variant = $item->variants()->first();
        $this->assertNotNull($variant);
        $this->assertSame(0, $variant->stock_quantity);

        $edit = Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->assertFormSet([
                'merchandise_supplier_id' => $supplier->getKey(),
            ])
            ->assertFormFieldExists('supplier_code');

        $stockLevels = $edit->get('data.stock_levels');
        $this->assertIsArray($stockLevels);
        $this->assertCount(1, $stockLevels);
        $stockKey = array_key_first($stockLevels);
        $this->assertSame($variant->getKey(), $stockLevels[$stockKey]['id']);
        $this->assertSame(0, (int) $stockLevels[$stockKey]['stock_quantity']);

        $stockLevels[$stockKey]['stock_quantity'] = 12;

        $edit->fillForm([
            'supplier_code' => 'POLO-42',
            'stock_levels' => $stockLevels,
        ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(12, $variant->fresh()->stock_quantity);
        $this->assertSame('POLO-42', $item->fresh()->supplier_code);

        Livewire::test(ViewMerchandiseItem::class, ['record' => $item->getKey()])
            ->assertSuccessful()
            ->assertSeeText('POLO-42');

        Livewire::test(ListMerchandiseItems::class)
            ->filterTable('supplier', ['value' => $supplier->getKey()])
            ->assertCanSeeTableRecords([$item])
            ->filterTable('supplier', ['value' => '__none'])
            ->assertCanNotSeeTableRecords([$item]);

        $unlinked = MerchandiseItem::factory()->for($team)->create([
            'title' => 'No supplier item',
            'merchandise_supplier_id' => null,
        ]);

        Livewire::test(ListMerchandiseItems::class)
            ->filterTable('supplier', ['value' => '__none'])
            ->assertCanSeeTableRecords([$unlinked])
            ->assertCanNotSeeTableRecords([$item]);
    }

    public function test_supplier_cannot_be_deleted_while_linked_to_items(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $supplier = MerchandiseSupplier::factory()->for($team)->create();
        MerchandiseItem::factory()->for($team)->create([
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertFalse($manager->can('delete', $supplier));

        $this->expectException(ValidationException::class);
        $supplier->delete();
    }

    public function test_unused_supplier_can_be_deleted(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $supplier = MerchandiseSupplier::factory()->for($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->assertTrue($manager->can('delete', $supplier));

        Livewire::test(EditMerchandiseSupplier::class, ['record' => $supplier->getKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('merchandise_suppliers', [
            'id' => $supplier->getKey(),
        ]);
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
        $this->assertSame(0, $item->variants->first()->stock_quantity);
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

    public function test_manager_can_discount_approve_and_reject_orders(): void
    {
        Notification::fake();
        Mail::fake();

        $team = Team::factory()->withShop()->create([
            'treasurer_email' => 'treasurer@example.test',
        ]);
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $outsider = User::factory()->teamMember($team)->create();

        $order = MerchandiseOrder::factory()->for($team)->for($member)->create();
        $line = $order->lines()->create([
            'item_title' => 'Polo',
            'variant_label' => 'M',
            'unit_price' => '20.00',
            'approved_unit_price' => '20.00',
            'quantity' => 2,
            'sort' => 0,
        ]);

        $this->assertSame(40.0, $order->fresh()->load('lines')->total());
        $this->assertSame(MerchandiseOrderPaymentStatus::None, $order->paymentStatus());

        $this->actingAs($outsider);
        $this->assertFalse($outsider->can('approve', $order));
        $this->assertFalse($outsider->can('discount', $order));

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        app(SetMerchandiseOrderLinePriceAction::class)($line, $manager, '15.00');
        $this->assertSame('15.00', $line->fresh()->approved_unit_price);
        $this->assertSame(30.0, $order->fresh()->load('lines')->itemsSubtotal());

        app(SetMerchandiseOrderDiscountAction::class)($order->fresh()->load('lines'), $manager, '5.00');
        $this->assertSame(25.0, $order->fresh()->load('lines')->total());

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $order->fresh(),
            'pageClass' => ViewMerchandiseOrder::class,
        ])
            ->callTableAction('discountLine', $line->fresh(), [
                'approved_unit_price' => '10.00',
                'discount' => '10.00',
            ])
            ->assertHasNoErrors()
            ->assertDispatched('merchandise-order-totals-updated');

        $this->assertSame(15.0, $order->fresh()->load('lines')->total());

        $issuedUrl = IssuedMerchandiseResource::getUrl('index', [
            'filters' => [
                'user_id' => [
                    'value' => $member->getKey(),
                ],
            ],
        ]);

        Livewire::test(ViewMerchandiseOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful()
            ->assertActionExists('viewIssued')
            ->assertActionHasUrl('viewIssued', $issuedUrl)
            ->assertActionDoesNotExist('approve')
            ->assertActionDoesNotExist('reject');

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $order->fresh(),
            'pageClass' => ViewMerchandiseOrder::class,
        ])
            ->assertActionExists('approve')
            ->assertActionExists('reject')
            ->callAction('approve', [
                'approval_note' => 'Polo allowance applied',
                'treasurer_email' => 'treasurer@example.test',
            ])
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(MerchandiseOrderStatus::Approved, $order->status);
        $this->assertNotNull($order->approved_at);
        $this->assertSame('Polo allowance applied', $order->approval_note);
        $this->assertSame('15.00', $order->amount_due);
        $this->assertNull($order->paid_at);
        $this->assertTrue($order->isUnpaid());
        $this->assertFalse($manager->can('approve', $order));
        Notification::assertNotSentTo($member, MerchandiseOrderApprovedNotification::class);
        Mail::assertQueued(MerchandiseOrderTreasurerRequestMail::class, function (MerchandiseOrderTreasurerRequestMail $mail) use ($order): bool {
            return $mail->order->is($order);
        });

        Livewire::test(ViewMerchandiseOrder::class, ['record' => $order->getKey()])
            ->assertActionExists('markPaid')
            ->assertActionExists('requestTreasurerInvoice')
            ->callAction('markPaid')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertFalse($manager->can('markPaid', $order));

        $rejectable = MerchandiseOrder::factory()->for($team)->for($member)->withLine([
            'unit_price' => '10.00',
            'approved_unit_price' => '10.00',
            'quantity' => 1,
        ])->create();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $rejectable,
            'pageClass' => ViewMerchandiseOrder::class,
        ])
            ->callAction('reject', ['rejection_reason' => 'Please fix the name spelling'])
            ->assertHasNoErrors();

        $rejectable->refresh();
        $this->assertSame(MerchandiseOrderStatus::Rejected, $rejectable->status);
        $this->assertSame('Please fix the name spelling', $rejectable->rejection_reason);
        $this->assertNotSame(MerchandiseOrderStatus::Cancelled, $rejectable->status);
        Notification::assertSentTo($member, MerchandiseOrderRejectedNotification::class);

        $ownOrder = MerchandiseOrder::factory()->for($team)->for($manager)->withLine([
            'unit_price' => '8.00',
            'approved_unit_price' => '8.00',
            'quantity' => 1,
        ])->create();

        app(ApproveMerchandiseOrderAction::class)($ownOrder, $manager, null, 'treasurer@example.test');
        Notification::assertNotSentTo($manager, MerchandiseOrderApprovedNotification::class);

        $ownRejectable = MerchandiseOrder::factory()->for($team)->for($manager)->withLine([
            'unit_price' => '8.00',
            'approved_unit_price' => '8.00',
            'quantity' => 1,
        ])->create();

        app(RejectMerchandiseOrderAction::class)($ownRejectable, $manager, 'Changing my mind');
        Notification::assertNotSentTo($manager, MerchandiseOrderRejectedNotification::class);

        $this->expectException(ValidationException::class);
        app(SetMerchandiseOrderLinePriceAction::class)($line->fresh(), $manager, '10.00');
    }

    public function test_approving_a_zero_due_order_marks_paid_and_notifies_without_treasurer_mail(): void
    {
        Notification::fake();
        Mail::fake();

        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();

        $order = MerchandiseOrder::factory()->for($team)->for($member)->withLine([
            'unit_price' => '10.00',
            'approved_unit_price' => '10.00',
            'quantity' => 1,
        ])->create();

        app(SetMerchandiseOrderLinePriceAction::class)($order->lines->first(), $manager, '0.00');
        app(ApproveMerchandiseOrderAction::class)($order->fresh()->load('lines'), $manager);

        $order->refresh();
        $this->assertSame('0.00', $order->amount_due);
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->isPaid());
        Notification::assertSentTo($member, MerchandiseOrderApprovedNotification::class);
        Mail::assertNothingQueued();
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

    public function test_manager_can_set_sku_prices_filtered_by_axis_value(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->draft()->create(['title' => 'Hoody']);
        $item->variants()->delete();

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

        $mBlack = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M · Black',
            'price' => '10.00',
            'sort' => 0,
        ]);
        $mBlack->optionValues()->sync([$sizeM->getKey(), $black->getKey()]);

        $lBlack = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'L · Black',
            'price' => '11.00',
            'sort' => 1,
        ]);
        $lBlack->optionValues()->sync([$sizeL->getKey(), $black->getKey()]);

        $mOrange = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M · Orange',
            'price' => '12.00',
            'sort' => 2,
        ]);
        $mOrange->optionValues()->sync([$sizeM->getKey(), $orange->getKey()]);

        $lOrange = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'L · Orange',
            'price' => '13.00',
            'sort' => 3,
        ]);
        $lOrange->optionValues()->sync([$sizeL->getKey(), $orange->getKey()]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $component = Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->callFormComponentAction('skus', 'setPrice', [
                'filters' => [
                    $colour->getKey() => $black->getKey(),
                ],
                'price' => '25.50',
            ]);

        $pricesByLabel = collect($component->get('data.variants'))
            ->mapWithKeys(fn (array $variant): array => [
                (string) ($variant['label'] ?? '') => (string) ($variant['price'] ?? ''),
            ])
            ->all();

        $this->assertSame('25.50', $pricesByLabel['M · Black']);
        $this->assertSame('25.50', $pricesByLabel['L · Black']);
        $this->assertEqualsWithDelta(12.0, (float) $pricesByLabel['M · Orange'], 0.001);
        $this->assertEqualsWithDelta(13.0, (float) $pricesByLabel['L · Orange'], 0.001);

        $component
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('25.50', $mBlack->fresh()->price);
        $this->assertSame('25.50', $lBlack->fresh()->price);
        $this->assertSame('12.00', $mOrange->fresh()->price);
        $this->assertSame('13.00', $lOrange->fresh()->price);
    }

    public function test_manager_can_set_all_sku_prices_when_filters_are_any_value(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $item = MerchandiseItem::factory()->for($team)->draft()->create(['title' => 'Necker']);
        $first = $item->variants()->firstOrFail();
        $first->update(['label' => 'Adult', 'price' => '8.00']);
        $second = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'Youth',
            'price' => '6.00',
            'sort' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $component = Livewire::test(EditMerchandiseItem::class, ['record' => $item->getKey()])
            ->callFormComponentAction('skus', 'setPrice', [
                'price' => '9.00',
            ]);

        $prices = collect($component->get('data.variants'))
            ->pluck('price')
            ->map(fn (mixed $price): string => (string) $price)
            ->all();

        $this->assertSame(['9.00', '9.00'], $prices);

        $component
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('9.00', $first->fresh()->price);
        $this->assertSame('9.00', $second->fresh()->price);
    }

    public function test_manager_can_save_option_value_notes_without_changing_composed_labels(): void
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
                            [
                                'id' => $sizeS->getKey(),
                                'label' => 'S',
                                'note' => '36-38"',
                            ],
                            [
                                'id' => $sizeM->getKey(),
                                'label' => 'M',
                                'note' => null,
                            ],
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
                        ],
                    ],
                    [
                        'price' => '22.00',
                        'availability' => MerchandiseAvailability::Draft->value,
                        'option_value_ids' => [
                            $size->getKey() => $sizeM->getKey(),
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh()->load(['optionAxes.values', 'variants']);

        $savedS = $item->optionAxes->first()->values->firstWhere('label', 'S');
        $savedM = $item->optionAxes->first()->values->firstWhere('label', 'M');

        $this->assertNotNull($savedS);
        $this->assertNotNull($savedM);
        $this->assertSame('36-38"', $savedS->note);
        $this->assertNull($savedM->note);
        $this->assertSame('S (36-38")', $savedS->selectOptionText());
        $this->assertSame('M', $savedM->selectOptionText());

        $this->assertNotNull($item->variants->firstWhere('label', 'S'));
        $this->assertNotNull($item->variants->firstWhere('label', 'M'));
        $this->assertNull($item->variants->firstWhere('label', 'S (36-38")'));
    }

    public function test_shop_option_selects_show_value_notes_when_present(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create();
        $item = MerchandiseItem::factory()->for($team)->withImage()->create(['title' => 'Hoody']);
        $item->variants()->delete();

        $size = MerchandiseItemOptionAxis::factory()->for($item, 'item')->create([
            'name' => 'Size',
            'sort' => 0,
        ]);
        $sizeS = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create([
            'label' => 'S',
            'note' => '36-38"',
            'sort' => 0,
        ]);
        $sizeM = MerchandiseItemOptionValue::factory()->for($size, 'axis')->create([
            'label' => 'M',
            'sort' => 1,
        ]);

        $sVariant = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'S',
            'price' => '20.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 0,
        ]);
        $sVariant->optionValues()->sync([$sizeS->getKey()]);

        $mVariant = MerchandiseItemVariant::factory()->for($item, 'item')->create([
            'label' => 'M',
            'price' => '22.00',
            'availability' => MerchandiseAvailability::Available,
            'sort' => 1,
        ]);
        $mVariant->optionValues()->sync([$sizeM->getKey()]);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewMerchandiseShop::class, ['record' => $item->getKey()])
            ->assertSuccessful()
            ->assertSee('S (36-38")')
            ->assertSee('M')
            ->assertDontSee('M (');
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

    public function test_managers_can_open_supplier_order_board_when_shop_is_on(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $this->get(MerchandiseSupplierOrderResource::getUrl('index', tenant: $team))->assertOk();
        $this->assertTrue(MerchandiseSupplierOrderResource::canAccess());

        $this->actingAs($member);
        Filament::setTenant($team);
        $this->assertFalse($member->can('viewAny', MerchandiseSupplierOrder::class));
    }

    public function test_supplier_order_picker_hides_suppliers_with_nothing_to_order(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $idle = MerchandiseSupplier::factory()->for($team)->create(['name' => 'Idle Co']);
        $active = MerchandiseSupplier::factory()->for($team)->create(['name' => 'Active Co']);
        $item = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Polo',
            'merchandise_supplier_id' => $active->getKey(),
        ]);
        $order = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $item->getKey(),
            'merchandise_item_variant_id' => $item->variants()->firstOrFail()->getKey(),
            'item_title' => 'Polo',
            'variant_label' => 'One size',
            'quantity' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ManageMerchandiseSupplierOrders::class)
            ->assertSuccessful()
            ->assertFormFieldExists('supplier_id', function (Select $select) use ($active, $idle): bool {
                $options = $select->getOptions();

                return isset($options[$active->getKey()])
                    && ! isset($options[$idle->getKey()]);
            });
    }

    public function test_supplier_order_draft_sync_send_and_board_candidates(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $supplier = MerchandiseSupplier::factory()->for($team)->create(['name' => 'Fox kit']);
        $item = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Polo',
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);
        $variant = $item->variants()->firstOrFail();
        $variant->update(['stock_quantity' => 0, 'label' => 'M · Black']);

        $order = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        $line = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $item->getKey(),
            'merchandise_item_variant_id' => $variant->getKey(),
            'item_title' => 'Polo',
            'variant_label' => 'M · Black',
            'quantity' => 1,
            'personalisation' => [
                ['key' => 'name', 'label' => 'Name', 'value' => 'Alex'],
            ],
        ]);

        $stockedItem = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Necker',
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);
        $stockedVariant = $stockedItem->variants()->firstOrFail();
        $stockedVariant->update(['stock_quantity' => 5, 'label' => 'One size']);
        $stockedOrder = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        $stockedLine = MerchandiseOrderLine::factory()->for($stockedOrder, 'order')->create([
            'merchandise_item_id' => $stockedItem->getKey(),
            'merchandise_item_variant_id' => $stockedVariant->getKey(),
            'item_title' => 'Necker',
            'variant_label' => 'One size',
            'quantity' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $candidates = MerchandiseOrderLine::query()
            ->boardCandidatesForSupplier($team, $supplier)
            ->pluck('id');
        $this->assertTrue($candidates->contains($line->getKey()));
        $this->assertTrue($candidates->contains($stockedLine->getKey()));

        $this->assertNull(MerchandiseSupplierOrder::draftFor($team, $supplier));

        $draft = app(SyncMerchandiseSupplierOrderDraftLinesAction::class)
            ->syncForSupplier($team, $supplier, $manager, [$line->getKey()]);

        $this->assertInstanceOf(MerchandiseSupplierOrder::class, $draft);
        $this->assertTrue($draft->isDraft());
        $this->assertSame($draft->getKey(), $line->fresh()->merchandise_supplier_order_id);
        $this->assertNull($stockedLine->fresh()->merchandise_supplier_order_id);
        $this->assertTrue($line->fresh()->canBeAmended());

        $same = app(EnsureMerchandiseSupplierOrderDraftAction::class)($team, $supplier, $manager);
        $this->assertTrue($same->is($draft));

        $sent = app(SendMerchandiseSupplierOrderAction::class)($draft, $manager);
        $this->assertTrue($sent->isSent());
        $this->assertNull($sent->open_draft_key);
        $this->assertFalse($line->fresh()->canBeAmended());
        $this->assertTrue($line->fresh()->isOnSentSupplierOrder());

        $candidatesAfterSend = MerchandiseOrderLine::query()
            ->boardCandidatesForSupplier($team, $supplier)
            ->pluck('id');
        $this->assertFalse($candidatesAfterSend->contains($line->getKey()));
        $this->assertTrue($candidatesAfterSend->contains($stockedLine->getKey()));

        Livewire::test(ManageMerchandiseSupplierOrders::class, [
            'supplierId' => $supplier->getKey(),
            'orderKey' => 'draft',
        ])
            ->assertSuccessful()
            ->assertSee('Necker')
            ->assertSee('Current draft');

        Livewire::test(ManageMerchandiseSupplierOrders::class, [
            'supplierId' => $supplier->getKey(),
            'orderKey' => $sent->getKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Polo')
            ->assertSee('Alex')
            ->assertDontSee('Necker')
            ->assertSee('Download CSV');

        Livewire::test(ViewMerchandiseSupplierOrder::class, ['record' => $sent->getKey()])
            ->assertSuccessful()
            ->assertSee('Polo')
            ->assertActionExists('downloadCsv');
    }

    public function test_manager_can_record_whole_line_goods_in_without_stock_bump(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $supplier = MerchandiseSupplier::factory()->for($team)->create(['name' => 'Fox kit']);
        $item = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Polo',
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);
        $variant = $item->variants()->firstOrFail();
        $variant->update(['stock_quantity' => 3]);

        $order = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        $line = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $item->getKey(),
            'merchandise_item_variant_id' => $variant->getKey(),
            'item_title' => 'Polo',
            'variant_label' => 'One size',
            'quantity' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $draft = app(SyncMerchandiseSupplierOrderDraftLinesAction::class)
            ->syncForSupplier($team, $supplier, $manager, [$line->getKey()]);
        $sent = app(SendMerchandiseSupplierOrderAction::class)($draft, $manager);

        Livewire::test(ManageMerchandiseSupplierOrders::class, [
            'supplierId' => $supplier->getKey(),
            'orderKey' => $sent->getKey(),
        ])
            ->assertSuccessful()
            ->assertSee('(0/1)')
            ->call('toggleArrived', (string) $line->getKey())
            ->assertSuccessful()
            ->assertSee('(complete)');

        $this->assertNotNull($line->fresh()->arrived_at);
        $this->assertSame(3, $variant->fresh()->stock_quantity);

        Livewire::test(ManageMerchandiseSupplierOrders::class, [
            'supplierId' => $supplier->getKey(),
            'orderKey' => $sent->getKey(),
        ])
            ->call('toggleArrived', (string) $line->getKey())
            ->assertSuccessful()
            ->assertSee('(0/1)');

        $this->assertNull($line->fresh()->arrived_at);
        $this->assertSame(3, $variant->fresh()->stock_quantity);

        $this->expectException(ValidationException::class);
        app(ToggleMerchandiseOrderLineArrivedAction::class)($line->fresh(), $member);
    }

    public function test_supplier_order_exporter_unions_axis_and_personalisation_labels(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create(['name' => 'Sam Member']);
        $supplier = MerchandiseSupplier::factory()->for($team)->create();
        $polo = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Polo',
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);
        $axis = MerchandiseItemOptionAxis::factory()->for($polo, 'item')->create([
            'name' => 'Size',
            'sort' => 0,
        ]);
        $sizeM = MerchandiseItemOptionValue::factory()->for($axis, 'axis')->create([
            'label' => 'M',
            'sort' => 0,
        ]);
        $variant = $polo->variants()->firstOrFail();
        $variant->optionValues()->sync([$sizeM->getKey()]);
        $variant->update(['label' => 'M']);

        $necker = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Necker',
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);

        $order = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        $poloLine = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $polo->getKey(),
            'merchandise_item_variant_id' => $variant->getKey(),
            'item_title' => 'Polo',
            'variant_label' => 'M',
            'quantity' => 1,
            'personalisation' => null,
        ]);
        $neckerLine = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $necker->getKey(),
            'merchandise_item_variant_id' => $necker->variants()->firstOrFail()->getKey(),
            'item_title' => 'Necker',
            'variant_label' => 'One size',
            'quantity' => 1,
            'personalisation' => [
                ['key' => 'name', 'label' => 'Name', 'value' => 'Alex'],
            ],
            'sort' => 1,
        ]);

        $supplierOrder = MerchandiseSupplierOrder::factory()->forSupplier($supplier)->sent()->create();
        $poloLine->update(['merchandise_supplier_order_id' => $supplierOrder->getKey()]);
        $neckerLine->update(['merchandise_supplier_order_id' => $supplierOrder->getKey()]);

        $table = app(MerchandiseSupplierOrderExporter::class)->table($supplierOrder->fresh());

        $this->assertSame(['Member', 'Item', 'Qty', 'Size', 'Name'], $table['headers']);
        $this->assertContains(['Sam Member', 'Polo', '1', 'M', ''], $table['rows']);
        $this->assertContains(['Sam Member', 'Necker', '1', '', 'Alex'], $table['rows']);

        $csv = app(MerchandiseSupplierOrderExporter::class)->toCsv($supplierOrder->fresh());
        $this->assertStringContainsString('Size,Name', $csv);
        $this->assertStringContainsString('Alex', $csv);
    }

    public function test_supplier_order_exporter_includes_supplier_code_when_any_item_has_one(): void
    {
        $team = Team::factory()->withShop()->create();
        $member = User::factory()->teamMember($team)->create(['name' => 'Sam Member']);
        $supplier = MerchandiseSupplier::factory()->for($team)->create();
        $polo = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Polo',
            'merchandise_supplier_id' => $supplier->getKey(),
            'supplier_code' => 'POLO-42',
        ]);
        $necker = MerchandiseItem::factory()->for($team)->create([
            'title' => 'Necker',
            'merchandise_supplier_id' => $supplier->getKey(),
            'supplier_code' => null,
        ]);

        $order = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        $poloLine = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $polo->getKey(),
            'merchandise_item_variant_id' => $polo->variants()->firstOrFail()->getKey(),
            'item_title' => 'Polo',
            'variant_label' => 'One size',
            'quantity' => 2,
        ]);
        $neckerLine = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $necker->getKey(),
            'merchandise_item_variant_id' => $necker->variants()->firstOrFail()->getKey(),
            'item_title' => 'Necker',
            'variant_label' => 'One size',
            'quantity' => 1,
            'sort' => 1,
        ]);

        $supplierOrder = MerchandiseSupplierOrder::factory()->forSupplier($supplier)->sent()->create();
        $poloLine->update(['merchandise_supplier_order_id' => $supplierOrder->getKey()]);
        $neckerLine->update(['merchandise_supplier_order_id' => $supplierOrder->getKey()]);

        $table = app(MerchandiseSupplierOrderExporter::class)->table($supplierOrder->fresh());

        $this->assertSame(['Member', 'Item', 'Supplier code', 'Qty'], $table['headers']);
        $this->assertContains(['Sam Member', 'Polo', 'POLO-42', '2'], $table['rows']);
        $this->assertContains(['Sam Member', 'Necker', '', '1'], $table['rows']);

        $csv = app(MerchandiseSupplierOrderExporter::class)->toCsv($supplierOrder->fresh());
        $this->assertStringContainsString('"Supplier code",Qty', $csv);
        $this->assertStringContainsString('POLO-42', $csv);
    }

    public function test_empty_draft_is_removed_when_all_lines_unchecked(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $supplier = MerchandiseSupplier::factory()->for($team)->create();
        $item = MerchandiseItem::factory()->for($team)->create([
            'merchandise_supplier_id' => $supplier->getKey(),
        ]);
        $order = MerchandiseOrder::factory()->for($team)->for($member)->approved()->create();
        $line = MerchandiseOrderLine::factory()->for($order, 'order')->create([
            'merchandise_item_id' => $item->getKey(),
            'merchandise_item_variant_id' => $item->variants()->firstOrFail()->getKey(),
            'item_title' => $item->title,
            'variant_label' => 'One size',
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $draft = app(SyncMerchandiseSupplierOrderDraftLinesAction::class)
            ->syncForSupplier($team, $supplier, $manager, [$line->getKey()]);
        $this->assertNotNull($draft);

        $cleared = app(SyncMerchandiseSupplierOrderDraftLinesAction::class)
            ->syncForSupplier($team, $supplier, $manager, []);

        $this->assertNull($cleared);
        $this->assertDatabaseMissing('merchandise_supplier_orders', [
            'id' => $draft->getKey(),
        ]);
        $this->assertNull($line->fresh()->merchandise_supplier_order_id);
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
