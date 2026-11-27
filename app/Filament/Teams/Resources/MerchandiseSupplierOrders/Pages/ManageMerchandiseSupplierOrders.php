<?php

namespace App\Filament\Teams\Resources\MerchandiseSupplierOrders\Pages;

use App\Actions\SendMerchandiseSupplierOrderAction;
use App\Actions\SyncMerchandiseSupplierOrderDraftLinesAction;
use App\Enums\MerchandiseOrderStatus;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\MerchandiseSupplierOrderResource;
use App\Models\MerchandiseOrderLine;
use App\Models\MerchandiseSupplier;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use App\Models\User;
use App\Support\MerchandiseSupplierOrderExporter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageMerchandiseSupplierOrders extends Page
{
    protected static string $resource = MerchandiseSupplierOrderResource::class;

    protected static ?string $title = 'Supplier orders';

    protected string $view = 'filament-panels::pages.page';

    #[Url]
    public ?string $supplierId = null;

    #[Url]
    public string $orderKey = 'draft';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(MerchandiseSupplierOrderResource::canViewAny(), 403);

        if ($this->orderKey !== 'draft' && filled($this->orderKey)) {
            $order = MerchandiseSupplierOrder::query()
                ->whereBelongsTo($this->team())
                ->whereKey($this->orderKey)
                ->first();

            if (! $order instanceof MerchandiseSupplierOrder) {
                $this->orderKey = 'draft';
            } elseif ($order->isDraft()) {
                $this->orderKey = 'draft';
                $this->supplierId = $order->merchandise_supplier_id;
            } else {
                $this->supplierId = $order->merchandise_supplier_id;
            }
        }

        $this->form->fill([
            'supplier_id' => $this->supplierId,
            'order_key' => $this->orderKey === '' ? 'draft' : $this->orderKey,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Supplier orders');
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Choose supplier'))
                ->icon(Heroicon::OutlinedTruck)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Select::make('supplier_id')
                        ->label(__('Supplier'))
                        ->options(fn (): array => $this->supplierOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state): void {
                            $this->supplierId = $state;
                            $this->orderKey = 'draft';
                            $this->data['order_key'] = 'draft';
                        })
                        ->required(),
                    Select::make('order_key')
                        ->label(__('Order'))
                        ->options(fn (Get $get): array => $this->orderOptions($get('supplier_id')))
                        ->selectablePlaceholder(false)
                        ->live()
                        ->visible(fn (Get $get): bool => filled($get('supplier_id')))
                        ->afterStateUpdated(function (?string $state): void {
                            $this->orderKey = filled($state) ? $state : 'draft';
                        }),
                ]),
            Section::make(fn (): string => $this->boardSectionHeading())
                ->icon(fn (): Heroicon => $this->isViewingSentOrder()
                    ? Heroicon::OutlinedLockClosed
                    : Heroicon::OutlinedClipboardDocumentList)
                ->columns(1)
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'flush-table-panel',
                ])
                ->visible(fn (Get $get): bool => filled($get('supplier_id')))
                ->description(fn (): ?string => $this->isViewingSentOrder()
                    ? __('This order is locked. Copy from the table or download CSV.')
                    : __('Tick lines to include. A draft is created when you include the first line.'))
                ->headerActions([
                    Action::make('send')
                        ->label(__('Send'))
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading(__('Send supplier order'))
                        ->modalDescription(__('Sending locks this order with the lines you have included. You can then export those items as a CSV for your supplier.'))
                        ->visible(fn (): bool => ! $this->isViewingSentOrder())
                        ->disabled(fn (): bool => ! $this->draftHasLinesToSend())
                        ->action(fn () => $this->sendDraft()),
                    Action::make('downloadCsv')
                        ->label(__('Download CSV'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn (): bool => $this->isViewingSentOrder()
                            && $this->viewedSentOrder()?->lines()->exists())
                        ->action(fn (): StreamedResponse => $this->downloadSentCsv()),
                ])
                ->schema([
                    SchemaView::make('filament.teams.resources.merchandise-supplier-orders.export-table')
                        ->viewData(fn (): array => [
                            'table' => $this->boardTable(),
                        ]),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form'),
        ]);
    }

    public function toggleLine(string $lineId): void
    {
        if ($this->isViewingSentOrder()) {
            return;
        }

        $selected = collect($this->selectedLineIdsForCurrentDraft());

        if ($selected->contains($lineId)) {
            $selected = $selected->reject(fn (string $id): bool => $id === $lineId)->values();
        } else {
            $selected = $selected->push($lineId)->unique()->values();
        }

        $this->saveSelection($selected->all());
    }

    public function toggleSelectAll(): void
    {
        if ($this->isViewingSentOrder()) {
            return;
        }

        $supplier = $this->supplier();

        if (! $supplier instanceof MerchandiseSupplier) {
            return;
        }

        $allIds = $this->candidates($supplier->getKey())
            ->map(fn (MerchandiseOrderLine $line): string => (string) $line->getKey())
            ->values();

        $selected = collect($this->selectedLineIdsForCurrentDraft());

        if ($allIds->isNotEmpty() && $selected->count() === $allIds->count()) {
            $this->saveSelection([]);

            return;
        }

        $this->saveSelection($allIds->all());
    }

    /**
     * @param  list<string>  $selectedLineIds
     */
    public function saveSelection(array $selectedLineIds): void
    {
        $supplier = $this->supplier();
        $user = $this->actor();

        if (! $supplier instanceof MerchandiseSupplier) {
            return;
        }

        try {
            app(SyncMerchandiseSupplierOrderDraftLinesAction::class)
                ->syncForSupplier($this->team(), $supplier, $user, $selectedLineIds);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Could not update draft'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        }
    }

    public function sendDraft(): void
    {
        $draft = $this->currentDraft();
        $user = $this->actor();

        if (! $draft instanceof MerchandiseSupplierOrder) {
            return;
        }

        try {
            $sent = app(SendMerchandiseSupplierOrderAction::class)($draft, $user);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Could not send'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('Supplier order sent'))
            ->send();

        $this->orderKey = $sent->getKey();
        $this->data['order_key'] = $sent->getKey();
    }

    public function downloadSentCsv(): StreamedResponse
    {
        $order = $this->viewedSentOrder();
        abort_unless($order instanceof MerchandiseSupplierOrder, 404);

        $exporter = app(MerchandiseSupplierOrderExporter::class);
        $csv = $exporter->toCsv($order);

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv;
            },
            $exporter->csvFilename($order),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array{id: string, selected: bool, cells: list<string>}>,
     *     editable: bool
     * }
     */
    public function boardTable(): array
    {
        $exporter = app(MerchandiseSupplierOrderExporter::class);

        if ($this->isViewingSentOrder()) {
            $order = $this->viewedSentOrder();

            if (! $order instanceof MerchandiseSupplierOrder) {
                return ['headers' => [], 'rows' => [], 'editable' => false];
            }

            $order->loadMissing([
                'lines.order.user',
                'lines.variant.optionValues.axis',
                'lines.item.optionAxes',
            ]);

            return $exporter->board(
                lines: $order->lines,
                selectedLineIds: $order->lines->modelKeys(),
                editable: false,
                showOnHandHint: false,
            );
        }

        $supplier = $this->supplier();

        if (! $supplier instanceof MerchandiseSupplier) {
            return ['headers' => [], 'rows' => [], 'editable' => true];
        }

        $candidates = $this->candidates($supplier->getKey());

        return $exporter->board(
            lines: $candidates,
            selectedLineIds: $this->selectedLineIdsForCurrentDraft(),
            editable: true,
            showOnHandHint: true,
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    private function boardSectionHeading(): string
    {
        if ($this->isViewingSentOrder()) {
            $when = $this->viewedSentOrder()?->sent_at
                ?->timezone(config('app.timezone'))
                ?->format('j M Y H:i');

            return $when
                ? __('Sent order — :date', ['date' => $when])
                : __('Sent order');
        }

        return __('Current draft');
    }

    private function isViewingSentOrder(): bool
    {
        return $this->orderKey !== 'draft' && filled($this->orderKey);
    }

    private function draftHasLinesToSend(): bool
    {
        $draft = $this->currentDraft();

        return $draft instanceof MerchandiseSupplierOrder
            && $draft->lines()->exists();
    }

    /**
     * Suppliers with draft-board candidates or any supplier order (draft or sent).
     *
     * @return array<string, string>
     */
    private function supplierOptions(): array
    {
        $team = $this->team();

        return MerchandiseSupplier::query()
            ->forTeam($team)
            ->where(function (Builder $suppliers) use ($team): void {
                $suppliers
                    ->whereHas(
                        'supplierOrders',
                        fn (Builder $orders): Builder => $orders->whereBelongsTo($team),
                    )
                    ->orWhereExists(function ($query) use ($team): void {
                        $query->selectRaw('1')
                            ->from('merchandise_order_lines')
                            ->join(
                                'merchandise_items',
                                'merchandise_items.id',
                                '=',
                                'merchandise_order_lines.merchandise_item_id',
                            )
                            ->join(
                                'merchandise_orders',
                                'merchandise_orders.id',
                                '=',
                                'merchandise_order_lines.merchandise_order_id',
                            )
                            ->whereColumn(
                                'merchandise_items.merchandise_supplier_id',
                                'merchandise_suppliers.id',
                            )
                            ->where('merchandise_items.team_id', $team->getKey())
                            ->where('merchandise_orders.team_id', $team->getKey())
                            ->where('merchandise_orders.status', MerchandiseOrderStatus::Approved)
                            ->whereNull('merchandise_order_lines.merchandise_supplier_order_id');
                    });
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function orderOptions(?string $supplierId): array
    {
        if (blank($supplierId)) {
            return [];
        }

        $supplier = MerchandiseSupplier::query()
            ->forTeam($this->team())
            ->whereKey($supplierId)
            ->first();

        if (! $supplier instanceof MerchandiseSupplier) {
            return [];
        }

        $options = [
            'draft' => __('Current draft'),
        ];

        $sent = MerchandiseSupplierOrder::query()
            ->whereBelongsTo($this->team())
            ->whereBelongsTo($supplier, 'supplier')
            ->sent()
            ->withCount('lines')
            ->latest('sent_at')
            ->get();

        foreach ($sent as $order) {
            $when = $order->sent_at?->timezone(config('app.timezone'))?->format('j M Y H:i');
            $options[$order->getKey()] = __(':date (:count lines)', [
                'date' => $when ?? __('Sent'),
                'count' => $order->lines_count,
            ]);
        }

        return $options;
    }

    /**
     * @return Collection<int, MerchandiseOrderLine>
     */
    private function candidates(?string $supplierId): Collection
    {
        if (blank($supplierId)) {
            return collect();
        }

        $supplier = MerchandiseSupplier::query()
            ->forTeam($this->team())
            ->whereKey($supplierId)
            ->first();

        if (! $supplier instanceof MerchandiseSupplier) {
            return collect();
        }

        return MerchandiseOrderLine::query()
            ->boardCandidatesForSupplier($this->team(), $supplier)
            ->with(['order.user', 'variant.optionValues.axis', 'item'])
            ->get();
    }

    /**
     * @return list<string>
     */
    private function selectedLineIdsForCurrentDraft(): array
    {
        $draft = $this->currentDraft();

        if (! $draft instanceof MerchandiseSupplierOrder) {
            return [];
        }

        return $draft->lines()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
    }

    private function currentDraft(): ?MerchandiseSupplierOrder
    {
        $supplier = $this->supplier();

        if (! $supplier instanceof MerchandiseSupplier) {
            return null;
        }

        return MerchandiseSupplierOrder::draftFor($this->team(), $supplier);
    }

    private function viewedSentOrder(): ?MerchandiseSupplierOrder
    {
        if (! $this->isViewingSentOrder()) {
            return null;
        }

        return MerchandiseSupplierOrder::query()
            ->whereBelongsTo($this->team())
            ->whereKey($this->orderKey)
            ->sent()
            ->first();
    }

    private function supplier(): ?MerchandiseSupplier
    {
        $supplierId = $this->supplierId ?? ($this->data['supplier_id'] ?? null);

        if (blank($supplierId)) {
            return null;
        }

        return MerchandiseSupplier::query()
            ->forTeam($this->team())
            ->whereKey($supplierId)
            ->first();
    }

    private function team(): Team
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Team, 404);

        return $tenant;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
