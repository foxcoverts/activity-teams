<?php

namespace App\Filament\Teams\Resources\Documents;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\Documents\Pages\ListDocuments;
use App\Filament\Teams\Resources\Documents\Pages\ViewDocument;
use App\Filament\Teams\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'document';

    protected static ?string $pluralModelLabel = 'documents';

    protected static ?string $slug = 'documents';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(
                $user instanceof User,
                fn (Builder $query): Builder => $query->forUser($user),
                fn (Builder $query): Builder => $query->whereRaw('0 = 1'),
            );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Document'))
                ->icon(Heroicon::OutlinedDocument)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('title'),
                    TextEntry::make('notes')
                        ->state(fn (Document $record): ?HtmlString => filled($record->notes)
                            ? new HtmlString(Str::inlineMarkdown($record->notes))
                            : null)
                        ->placeholder('-'),
                    TextEntry::make('updated_at')
                        ->label(__('Updated'))
                        ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                        ->dateTime('j M Y, H:i'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'view' => ViewDocument::route('/{record}'),
        ];
    }
}
