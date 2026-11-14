<?php

namespace App\Filament\Member\Pages;

use App\Filament\Bookings\LogbookEntryAction;
use App\Filament\Member\NavigationGroup;
use App\Models\Booking;
use App\Models\LogbookEntry;
use App\Models\User;
use App\Support\Logbook as LogbookSupport;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Logbook extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Personal;

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $slug = 'logbook';

    protected string $view = 'filament-panels::pages.page';

    public static function getNavigationLabel(): string
    {
        return __('Logbook');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Personal logbook');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = app(LogbookSupport::class)->outstandingCount($user);

        return $count > 0 ? (string) $count : null;
    }

    public function mount(): void
    {
        abort_unless(auth()->user() instanceof User, 403);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            SchemaView::make('filament.member.pages.logbook-outstanding')
                ->columnSpanFull(),
            EmbeddedTable::make()
                ->columnSpanFull(),
        ]);
    }

    /**
     * @return Collection<int, Booking>
     */
    public function outstandingBookings(): Collection
    {
        return app(LogbookSupport::class)->outstanding($this->actor());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(LogbookSupport::class)->entriesQuery($this->actor()))
            ->heading(__('Saved log'))
            ->description(__('Sessions you have added to your personal experience logbook.'))
            ->columns([
                TextColumn::make('booking.start_at')
                    ->label(__('Schedule'))
                    ->state(function (LogbookEntry $record): string {
                        $booking = $record->booking;

                        if ($booking === null || $booking->start_at === null) {
                            return '—';
                        }

                        return $booking->start_at
                            ->copy()
                            ->timezone($booking->timezone)
                            ->toFormattedDayDateString()
                            .' '
                            .$booking->start_at->copy()->timezone($booking->timezone)->format('H:i');
                    }),
                TextColumn::make('booking.activity')
                    ->label(__('Activity'))
                    ->state(fn (LogbookEntry $record): string => $record->booking?->activityTitle() ?? '—'),
                TextColumn::make('booking.venue.name')
                    ->label(__('Venue'))
                    ->placeholder('—'),
                TextColumn::make('hours')
                    ->label(__('Hours'))
                    ->placeholder('—'),
                TextColumn::make('participant_count')
                    ->label(__('Participants'))
                    ->placeholder('—'),
                TextColumn::make('weather')
                    ->label(__('Weather'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('schedule')
                    ->label(__('Schedule'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('From')),
                        DatePicker::make('to')
                            ->label(__('To')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return app(LogbookSupport::class)->applyEntryFilters($query, [
                            'from' => $data['from'] ?? null,
                            'to' => $data['to'] ?? null,
                        ]);
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = __('From :date', ['date' => $data['from']]);
                        }

                        if (filled($data['to'] ?? null)) {
                            $indicators[] = __('To :date', ['date' => $data['to']]);
                        }

                        return $indicators;
                    }),
                SelectFilter::make('activity_id')
                    ->label(__('Activity'))
                    ->multiple()
                    ->options(fn (): array => app(LogbookSupport::class)->activityFilterOptions($this->actor()))
                    ->query(function (Builder $query, array $data): Builder {
                        return app(LogbookSupport::class)->applyEntryFilters($query, [
                            'activity' => $data['values'] ?? [],
                        ]);
                    }),
                SelectFilter::make('team_id')
                    ->label(__('Team'))
                    ->options(fn (): array => app(LogbookSupport::class)->teamFilterOptions($this->actor()))
                    ->query(function (Builder $query, array $data): Builder {
                        return app(LogbookSupport::class)->applyEntryFilters($query, [
                            'team' => $data['value'] ?? null,
                        ]);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('Edit'))
                        ->modalHeading(__('Edit logbook entry'))
                        ->modalWidth(Width::Large)
                        ->schema(function (LogbookEntry $record): array {
                            $booking = $record->booking;
                            assert($booking instanceof Booking);

                            return LogbookEntryAction::schema($booking);
                        })
                        ->fillForm(fn (LogbookEntry $record): array => LogbookEntryAction::editFormState($record))
                        ->extraModalFooterActions(fn (Action $action): array => [
                            LogbookEntryAction::copyFromDebriefAction($action),
                        ])
                        ->action(function (LogbookEntry $record, array $data): void {
                            try {
                                app(LogbookSupport::class)->update($this->actor(), $record, $data);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to save.'))
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title(__('Logbook entry saved'))
                                ->send();
                        }),
                    DeleteAction::make()
                        ->label(__('Remove'))
                        ->modalHeading(__('Remove logbook entry?'))
                        ->modalDescription(__('This removes the entry from your saved log. The session will not return to Outstanding.'))
                        ->successNotificationTitle(__('Removed'))
                        ->action(function (LogbookEntry $record): void {
                            try {
                                app(LogbookSupport::class)->remove($this->actor(), $record);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to remove.'))
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading(__('No saved log entries yet'))
            ->emptyStateDescription(__('Confirm attendance on a booking first, or speak to the lead or team manager. Outstanding sessions appear above.'))
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label(__('Export CSV'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->exportCsv()),
            Action::make('exportPdf')
                ->label(__('Export PDF'))
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => route('member.logbook.export', $this->exportFilterQuery()))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array{from?: string, to?: string, activity?: list<string>, team?: string}
     */
    public function exportFilterQuery(): array
    {
        $schedule = $this->getTableFilterState('schedule') ?? [];
        $activity = $this->getTableFilterState('activity_id') ?? [];
        $team = $this->getTableFilterState('team_id') ?? [];

        return app(LogbookSupport::class)->normalisedFilters([
            'from' => $schedule['from'] ?? null,
            'to' => $schedule['to'] ?? null,
            'activity' => $activity['values'] ?? [],
            'team' => $team['value'] ?? null,
        ]);
    }

    public function addAction(): Action
    {
        return Action::make('add')
            ->label(__('Add'))
            ->icon(Heroicon::Plus)
            ->color(Palette::positive())
            ->modalHeading(__('Add to logbook'))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(function (array $arguments): array {
                $booking = $this->bookingFromArguments($arguments);

                return app(LogbookSupport::class)->addFormState($booking);
            })
            ->schema(fn (array $arguments): array => LogbookEntryAction::schema($this->bookingFromArguments($arguments)))
            ->action(function (array $arguments, array $data): void {
                $booking = $this->bookingFromArguments($arguments);

                try {
                    app(LogbookSupport::class)->add($this->actor(), $booking, $data, prefillFromDebrief: false);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to add this session.'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Added to logbook'))
                    ->send();
            });
    }

    public function ignoreAction(): Action
    {
        return Action::make('ignore')
            ->label(__('Ignore'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color(Palette::neutral())
            ->requiresConfirmation()
            ->modalHeading(__('Ignore this session?'))
            ->modalDescription(__('It will leave your outstanding list. You can still add it later if you change your mind.'))
            ->action(function (array $arguments): void {
                $booking = $this->bookingFromArguments($arguments);

                try {
                    app(LogbookSupport::class)->ignore($this->actor(), $booking);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to ignore this session.'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Removed from outstanding'))
                    ->send();
            });
    }

    public function declineAction(): Action
    {
        return Action::make('decline')
            ->label(__('Decline'))
            ->icon(Heroicon::XCircle)
            ->color(Palette::destructive())
            ->requiresConfirmation()
            ->modalHeading(__('Mark as did not attend?'))
            ->modalDescription(__('This updates your attendance on the booking and refreshes your calendar invite when one is open.'))
            ->visible(fn (array $arguments): bool => app(LogbookSupport::class)->canDecline($this->bookingFromArguments($arguments)))
            ->action(function (array $arguments): void {
                $booking = $this->bookingFromArguments($arguments);

                try {
                    app(LogbookSupport::class)->decline($this->actor(), $booking);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to decline this session.'))
                        ->send();

                    return;
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Attendance updated'))
                    ->send();
            });
    }

    private function exportCsv(): StreamedResponse
    {
        $logbook = app(LogbookSupport::class);
        $entries = $this->getFilteredTableQuery()
            ->with(['booking.venue', 'booking.activity', 'booking.audience'])
            ->get();
        $headers = $logbook->exportHeaders();

        return response()->streamDownload(function () use ($logbook, $entries, $headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($entries as $entry) {
                fputcsv($handle, array_map(
                    fn (?string $value): string => $value ?? '',
                    $logbook->exportRow($entry),
                ));
            }

            fclose($handle);
        }, 'logbook.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function bookingFromArguments(array $arguments): Booking
    {
        $booking = Booking::query()->find($arguments['booking'] ?? null);

        abort_unless($booking instanceof Booking, 404);

        return $booking;
    }

    private function actor(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
