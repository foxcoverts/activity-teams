@php
    use App\Enums\TeamFeature;
    use App\Filament\Teams\Resources\MerchandiseBasketLines\MerchandiseBasketLineResource;
    use App\Models\Team;
    use App\Models\User;
    use App\Support\Palette;
    use Filament\Facades\Filament;
    use Filament\Support\Icons\Heroicon;

    $user = Filament::auth()->user();
    $team = Filament::getTenant();
    $count = ($user instanceof User && $team instanceof Team && $team->featureEnabled(TeamFeature::Shop))
        ? MerchandiseBasketLineResource::quantityInBasket($team, $user)
        : 0;
    $label = trans_choice(
        ':count item in basket|:count items in basket',
        $count,
        ['count' => $count],
    );
@endphp

@if ($count > 0)
    <x-filament::icon-button
        :badge="(string) $count"
        :badge-color="Palette::informative()"
        color="gray"
        :href="MerchandiseBasketLineResource::getUrl(panel: 'teams', tenant: $team)"
        :icon="Heroicon::OutlinedShoppingCart"
        icon-size="lg"
        :label="$label"
        tag="a"
        :tooltip="$label"
    />
@endif
