<?php

namespace App\Filament\Member\Pages;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Support\MailPreference;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read Schema $form
 */
class NotificationSettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'notification-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected string $view = 'filament-panels::pages.page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('Notification settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Notifications');
    }

    public static function userMenuItem(): Action
    {
        return Action::make('notificationSettings')
            ->label(__('Notifications'))
            ->icon(Heroicon::OutlinedBell)
            ->url(fn (): string => static::getUrl(panel: 'member'))
            ->sort(0);
    }

    public function mount(): void
    {
        $this->form->fill($this->globalFormState());
    }

    public function updated(string $property): void
    {
        foreach ([MailGroup::Bookings, MailGroup::Keys] as $group) {
            $master = $group->masterChannel();

            if ($master instanceof MailChannel && $property === 'data.'.$master->formKey()) {
                $this->syncMasterDisplay($group);
            }
        }
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->groupSection(MailGroup::Bookings),
                $this->groupSection(MailGroup::Keys),
                Section::make(MailGroup::Manager->getLabel())
                    ->icon(MailGroup::Manager->getIcon())
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('manager_notice')
                            ->hiddenLabel()
                            ->content(__('Mail about leading a team — such as the last manager leaving, or a key holder deleting their account — is always recorded and included in the digest. That is part of being a manager. A booking that needs a lead, and a key returning to the cupboard, follow the booking and key settings above.')),
                    ]),
                Section::make()
                    ->schema([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save'))
                                ->submit('save'),
                            Action::make('resetDefaults')
                                ->label(__('Reset to defaults'))
                                ->color(Palette::neutral())
                                ->action('resetDefaults'),
                        ]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save'),
        ];

        if ($this->hasOverrides()) {
            $components[] = SchemaView::make('filament.member.pages.notification-overrides')
                ->columnSpanFull();
        }

        return $schema->components($components);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->overrideQuery())
            ->heading(__('Overrides'))
            ->description(__('These apply to one booking. Remove an override to inherit your global settings again.'))
            ->columns([
                TextColumn::make('subject')
                    ->label(__('For'))
                    ->state(fn (NotificationSetting $record): string => $record->subjectLabel()),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (NotificationSetting $record): array => $this->channelSelects(
                        $record->group(),
                        $record->subject instanceof Model ? $record->subject : null,
                    ))
                    ->fillForm(function (NotificationSetting $record): array {
                        $user = $this->actor();
                        $subject = $record->subject instanceof Model ? $record->subject : null;

                        return MailPreference::displayState($user, $record->group(), $subject);
                    })
                    ->action(function (NotificationSetting $record, array $data): void {
                        $subject = $record->subject instanceof Model ? $record->subject : null;

                        MailPreference::save($this->actor(), $record->group(), $data, $subject);
                        $this->form->fill($this->globalFormState());
                    }),
                DeleteAction::make()
                    ->label(__('Remove'))
                    ->modalHeading(__('Remove override'))
                    ->successNotificationTitle(__('Removed')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No overrides'))
            ->emptyStateDescription(__('Use the link in an email to change notifications for one booking.'));
    }

    public function hasOverrides(): bool
    {
        return $this->overrideQuery()->exists();
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = $this->actor();

        MailPreference::save($user, MailGroup::Bookings, $data);
        MailPreference::save($user->fresh(), MailGroup::Keys, $data);

        $this->form->fill($this->globalFormState());

        Notification::make()
            ->success()
            ->title(__('Notification settings saved'))
            ->send();
    }

    public function resetDefaults(): void
    {
        $user = $this->actor();

        MailPreference::save($user, MailGroup::Bookings, []);
        MailPreference::save($user->fresh(), MailGroup::Keys, []);

        $this->form->fill($this->globalFormState());

        Notification::make()
            ->success()
            ->title(__('Notification settings reset'))
            ->send();
    }

    /**
     * @return array<string, string>
     */
    private function globalFormState(): array
    {
        $user = $this->actor();

        return array_merge(
            MailPreference::displayState($user, MailGroup::Bookings),
            MailPreference::displayState($user, MailGroup::Keys),
        );
    }

    private function groupSection(MailGroup $group): Section
    {
        return Section::make($group->getLabel())
            ->icon($group->getIcon())
            ->columns(1)
            ->columnSpanFull()
            ->schema($this->channelSelects($group));
    }

    /**
     * @return list<Select>
     */
    private function channelSelects(MailGroup $group, ?Model $subject = null): array
    {
        $user = $this->actor();
        $master = $group->masterChannel();

        return array_map(
            function (MailChannel $channel) use ($user, $subject, $master, $group): Select {
                $inherited = MailPreference::inheritedValue($user, $channel, $subject);

                $select = Select::make($channel->formKey())
                    ->label($channel->getLabel())
                    ->helperText($channel->description())
                    ->placeholder(__('Default (:state)', ['state' => $inherited ? __('On') : __('Off')]))
                    ->options([
                        '1' => __('On'),
                        '0' => __('Off'),
                    ]);

                if ($master instanceof MailChannel && $channel === $master) {
                    $select->live();

                    if ($subject instanceof Model) {
                        $select->afterStateUpdated(function (Set $set, mixed $state) use ($user, $subject, $group, $master): void {
                            $stored = MailPreference::formState($user, $group, $subject);
                            $stored[$master->formKey()] = match ($state) {
                                '0', 0, false => '0',
                                '1', 1, true => '1',
                                default => is_string($state) ? $state : '',
                            };
                            $display = MailPreference::applyMasterDisplay($user, $group, $stored, $subject);

                            foreach ($group->shownChannels() as $shown) {
                                if ($shown->parent() instanceof MailChannel) {
                                    $set($shown->formKey(), $display[$shown->formKey()] ?? '0');
                                }
                            }
                        });
                    }
                }

                if ($master instanceof MailChannel && $channel->parent() instanceof MailChannel) {
                    $select->disabled(fn (Get $get): bool => $this->masterOff($get, $user, $master, $subject));
                }

                return $select;
            },
            $group->shownChannels(),
        );
    }

    private function syncMasterDisplay(MailGroup $group): void
    {
        $stored = MailPreference::formState($this->actor(), $group);
        $master = $group->masterChannel();

        if ($master instanceof MailChannel) {
            $stored[$master->formKey()] = $this->data[$master->formKey()] ?? '';
        }

        $display = MailPreference::applyMasterDisplay($this->actor(), $group, $stored);

        foreach ($group->shownChannels() as $channel) {
            if ($channel->parent() instanceof MailChannel) {
                $this->data[$channel->formKey()] = $display[$channel->formKey()] ?? '0';
            }
        }
    }

    private function masterOff(Get $get, User $user, MailChannel $master, ?Model $subject): bool
    {
        $form = $subject instanceof Model
            ? [$master->formKey() => $get($master->formKey())]
            : ($this->data ?? []);

        return MailPreference::formMasterOff($user, $master->group(), $form, $subject);
    }

    /**
     * @return Builder<NotificationSetting>
     */
    private function overrideQuery(): Builder
    {
        return NotificationSetting::query()
            ->where('user_id', $this->actor()->getKey())
            ->with('subject');
    }

    private function actor(): User
    {
        $user = Filament::auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
