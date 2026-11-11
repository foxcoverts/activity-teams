<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property-read Schema $form
 */
class Feeds extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'feeds';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

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
        $this->ensureFeedToken();
        $this->form->fill($this->feedLinkState());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Calendar'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->description(__('Subscribe in your calendar app. Treat these links like passwords.'))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        $this->feedLink(
                            'everything',
                            __('Everything'),
                            __('All bookings and tasks you can see.'),
                        ),
                        $this->feedLink(
                            'rota',
                            __('My rota'),
                            __('Bookings and tasks you have accepted.'),
                        ),
                        $this->feedLink(
                            'bookings',
                            __('All bookings'),
                            __('Every booking across your teams.'),
                        ),
                        $this->feedLink(
                            'tasks',
                            __('Tasks'),
                            __('Tasks across your teams.'),
                        ),
                    ]),
                Section::make(__('News'))
                    ->icon(Heroicon::OutlinedNewspaper)
                    ->description(__('Subscribe in a news reader. Treat these links like passwords.'))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        $this->feedLink('atom', __('Atom')),
                        $this->feedLink('rss', __('RSS')),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('form'),
            ]);
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

                    $this->form->fill($this->feedLinkState());

                    Notification::make()
                        ->success()
                        ->title(__('Feed links updated'))
                        ->send();
                }),
        ];
    }

    private function feedLink(string $name, string $label, ?string $helper = null): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->helperText($helper)
            ->password()
            ->revealable()
            ->suffixAction(
                CopyAction::make()
                    ->label(__('Copy link'))
                    ->tooltip(__('Copy link'))
                    ->copyMessage(__('Copied')),
            )
            ->readOnly()
            ->dehydrated(false);
    }

    /**
     * @return array<string, string>
     */
    private function feedLinkState(): array
    {
        $user = $this->ensureFeedToken();

        return [
            'everything' => route('calendar.everything.ics', $user),
            'rota' => route('booking.rota.ics', $user),
            'bookings' => route('booking.ics', $user),
            'tasks' => route('task.ics', $user),
            'atom' => route('news.atom', $user),
            'rss' => route('news.rss', $user),
        ];
    }

    private function ensureFeedToken(): User
    {
        $user = auth()->user();
        assert($user instanceof User);
        $user->ensureFeedToken();
        $user = $user->fresh();
        assert($user instanceof User);

        return $user;
    }
}
