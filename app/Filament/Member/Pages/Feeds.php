<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Feeds extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'feeds';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected string $view = 'filament.member.pages.feeds';

    public static function getNavigationLabel(): string
    {
        return __('Feeds');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Feeds');
    }

    public static function userMenuItem(): Action
    {
        return Action::make('feeds')
            ->label(__('Feeds'))
            ->icon(Heroicon::OutlinedRss)
            ->url(fn (): string => static::getUrl(panel: 'member'))
            ->sort(1);
    }

    public function mount(): void
    {
        $user = auth()->user();
        assert($user instanceof User);

        $user->ensureFeedToken();
    }

    /**
     * @return array<string, string>
     */
    public function getViewData(): array
    {
        $user = auth()->user();
        assert($user instanceof User);
        $user->ensureFeedToken();
        $user = $user->fresh();
        assert($user instanceof User);

        return [
            'bookingsUrl' => route('booking.ics', $user),
            'rotaUrl' => route('booking.rota.ics', $user),
            'atomUrl' => route('news.atom', $user),
            'rssUrl' => route('news.rss', $user),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label(__('Regenerate links'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color(Palette::destructive())
                ->requiresConfirmation()
                ->modalHeading(__('Regenerate feed links'))
                ->modalDescription(__('Existing calendar and news subscriptions will stop working until you add the new links.'))
                ->action(function (): void {
                    $user = auth()->user();
                    assert($user instanceof User);
                    $user->forceFill(['feed_token' => User::generateToken()])->save();

                    Notification::make()
                        ->success()
                        ->title(__('Feed links updated'))
                        ->send();
                }),
        ];
    }
}
