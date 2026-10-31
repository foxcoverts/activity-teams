<?php

namespace App\Filament\Admin\Resources\InboundMails\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InboundMailInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Message'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('Received'))
                            ->dateTime(),
                        TextEntry::make('from_address')
                            ->label(__('From'))
                            ->placeholder('-'),
                        TextEntry::make('to_address')
                            ->label(__('To'))
                            ->placeholder('-'),
                        TextEntry::make('subject')
                            ->label(__('Subject'))
                            ->placeholder('-'),
                        TextEntry::make('calendar_method')
                            ->label(__('Calendar method'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('outcome')
                            ->label(__('Outcome'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('outcome_detail')
                            ->label(__('Detail'))
                            ->placeholder('-'),
                        TextEntry::make('handled_at')
                            ->label(__('Handled'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('reviewed_at')
                            ->label(__('Reviewed'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('resource_type')
                            ->label(__('Resource'))
                            ->formatStateUsing(function ($state, $record): string {
                                if ($record->resource === null) {
                                    return '-';
                                }

                                return class_basename($record->resource_type).' · '.$record->resource_id;
                            })
                            ->placeholder('-'),
                    ]),
                Section::make(__('Diagnostic'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('body')
                            ->label(__('Raw payload'))
                            ->formatStateUsing(fn (?string $state): string => $state ?? '')
                            ->prose()
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
