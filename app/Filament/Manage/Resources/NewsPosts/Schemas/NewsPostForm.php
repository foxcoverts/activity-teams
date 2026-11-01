<?php

namespace App\Filament\Manage\Resources\NewsPosts\Schemas;

use App\Models\NewsPost;
use Filament\Facades\Filament;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class NewsPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Post'))
                    ->icon(Heroicon::OutlinedNewspaper)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required(),

                        Select::make('author_id')
                            ->relationship(
                                'author',
                                'name',
                                modifyQueryUsing: fn (NewsPost $record, Builder $query): Builder => $query
                                    ->whereRelation('teams', 'teams.id', $record->team_id)
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->required()
                            ->visible(fn (string $operation): bool => $operation === 'edit'),

                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->prefix(fn (NewsPost $record): string => '/news/'.($record->team?->slug ?? '{team}').'/')
                            ->helperText(__('From the title.'))
                            ->alphaDash()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule): Unique {
                                    $teamId = Filament::getTenant()?->getKey();

                                    return $rule->where('team_id', $teamId);
                                },
                            )
                            ->visible(fn (string $operation): bool => $operation === 'edit'),

                        MarkdownEditor::make('body')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
