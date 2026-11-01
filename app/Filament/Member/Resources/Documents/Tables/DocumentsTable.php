<?php

namespace App\Filament\Member\Resources\Documents\Tables;

use App\Filament\Documents\DownloadDocumentAction;
use App\Models\Document;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->description(fn (Document $record): ?HtmlString => filled($record->notes)
                        ? new HtmlString(Str::inlineMarkdown($record->notes))
                        : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('team.name')
                    ->label(__('Team')),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('team')
                    ->relationship(
                        'team',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $user = auth()->user();

                            if (! $user instanceof User) {
                                return $query->whereRaw('0 = 1');
                            }

                            return $query
                                ->whereIn('teams.id', $user->teams()->pluck('teams.id'))
                                ->orderBy('name');
                        },
                    )
                    ->preload(),
            ])
            ->recordActions([
                DownloadDocumentAction::make(),
            ])
            ->toolbarActions([]);
    }
}
