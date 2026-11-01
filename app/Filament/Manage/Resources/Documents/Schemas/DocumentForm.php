<?php

namespace App\Filament\Manage\Resources\Documents\Schemas;

use App\Models\Document;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Document'))
                    ->icon(Heroicon::OutlinedDocument)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('file_path')
                            ->label(__('File'))
                            ->disk('local')
                            ->directory(fn (): string => 'documents/'.(Filament::getTenant()?->getKey() ?? 'unassigned'))
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->minSize(1)
                            ->maxSize(10240)
                            ->preventFilePathTampering()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->downloadable()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                                static::fillNamesFromUpload($state, $old, $set, $get);
                            }),
                        TextInput::make('file_name')
                            ->label(__('Filename'))
                            ->helperText(__('This is the filename that a user will see when they download the document.'))
                            ->required()
                            ->maxLength(100)
                            ->regex('/^[\w\-. ]+$/')
                            ->visible(fn (Get $get): bool => filled($get('file_name'))),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        MarkdownEditor::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function fillNamesFromUpload(mixed $state, mixed $old, Set $set, Get $get): void
    {
        $originalName = static::originalNameFromUpload($state);

        if ($originalName === null) {
            return;
        }

        $title = (string) ($get('title') ?? '');
        $previousSource = static::originalNameFromUpload($old) ?? (string) ($get('file_name') ?? '');

        if ($previousSource !== '' && $title === Document::titleFromOriginal($previousSource)) {
            $title = '';
        }

        $set('file_name', Document::downloadNameFromOriginal($originalName));

        if ($title === '') {
            $set('title', Document::titleFromOriginal($originalName));
        }
    }

    private static function originalNameFromUpload(mixed $state): ?string
    {
        $file = Arr::first(Arr::wrap($state));

        if ($file instanceof UploadedFile) {
            return $file->getClientOriginalName();
        }

        return null;
    }
}
