@php
    use App\Filament\Member\Resources\Inbox\InboxResource;
    use App\Models\User;
    use App\Support\Palette;
    use Filament\Support\Icons\Heroicon;

    $user = filament()->auth()->user();
    $count = $user instanceof User ? InboxResource::unreadCount() : 0;
    $label = $count > 0
        ? trans_choice(':count unread notification|:count unread notifications', $count, ['count' => $count])
        : __('Inbox');
@endphp

@if ($user instanceof User)
    <x-filament::icon-button
        :badge="$count > 0 ? (string) $count : null"
        :badge-color="Palette::informative()"
        color="gray"
        :href="InboxResource::getUrl(panel: 'member')"
        :icon="Heroicon::OutlinedEnvelope"
        icon-size="lg"
        :label="$label"
        tag="a"
        :tooltip="$label"
    />
@endif
