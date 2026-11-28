<?php

namespace App\Notifications;

use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Models\MerchandiseOrder;
use App\Models\Team;
use App\Notifications\Concerns\RecordsInbox;
use App\Notifications\Contracts\ClearsWhenSubjectViewed;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MerchandiseOrderApprovedNotification extends Notification implements ClearsWhenSubjectViewed
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public MerchandiseOrder $order)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        if ((float) $this->order->amount_due <= 0) {
            return __('Your shop order was approved. There is nothing to pay.');
        }

        return __('Your shop order was approved.');
    }

    public function inboxUrl(object $notifiable): ?string
    {
        $team = $this->order->team;

        if (! $team instanceof Team) {
            return null;
        }

        return MerchandiseOrderResource::getUrl(
            'view',
            ['record' => $this->order],
            panel: 'teams',
            tenant: $team,
        );
    }

    public function inboxSubject(): ?MerchandiseOrder
    {
        return $this->order;
    }

    public function inboxTeam(?object $notifiable = null): ?Team
    {
        return $this->order->team;
    }

    public function inboxInAppOnly(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'merchandise_order_id' => $this->order->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $order = MerchandiseOrder::query()->findOrFail($data['rebuild']['merchandise_order_id']);

        return new self($order);
    }
}
