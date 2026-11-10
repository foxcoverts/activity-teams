<?php

namespace App\Filament\Teams\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Task'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('summary')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('Notes'))
                            ->rows(4),
                        TextInput::make('location')
                            ->maxLength(255),
                        Select::make('priority')
                            ->options(TaskPriority::class)
                            ->default(TaskPriority::Medium->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->options(TaskStatus::class)
                            ->default(TaskStatus::NeedsAction->value)
                            ->required()
                            ->native(false),
                        DateTimePicker::make('started_at')
                            ->label(__('Started'))
                            ->seconds(false),
                        DateTimePicker::make('due_at')
                            ->label(__('Due'))
                            ->seconds(false),
                    ]),
            ]);
    }
}
