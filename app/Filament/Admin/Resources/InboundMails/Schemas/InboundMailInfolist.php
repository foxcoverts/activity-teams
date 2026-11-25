<?php

namespace App\Filament\Admin\Resources\InboundMails\Schemas;

use App\Models\InboundMail;
use App\Models\User;
use App\Support\InboundMailResourceLink;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
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
                        TextEntry::make('resource')
                            ->label(__('Resource'))
                            ->state(fn (InboundMail $record): ?string => InboundMailResourceLink::label($record->resource))
                            ->url(fn (InboundMail $record): ?string => static::resourceUrl($record))
                            ->color(fn (InboundMail $record): ?string => static::resourceUrl($record) === null ? null : 'primary')
                            ->placeholder('-'),
                    ]),
                Section::make(__('Body'))
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->columns(1)
                    ->columnSpanFull()
                    ->visible(fn (InboundMail $record): bool => filled($record->messageText()))
                    ->schema([
                        TextEntry::make('message_text')
                            ->hiddenLabel()
                            ->state(fn (InboundMail $record): ?string => $record->messageText())
                            ->formatStateUsing(fn (?string $state): string => nl2br(e($state ?? ''), false))
                            ->html()
                            ->limit(null)
                            ->copyable()
                            ->copyableState(fn (InboundMail $record): ?string => $record->messageText())
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Diagnostic'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('attachments')
                            ->label(__('Attachments'))
                            ->state(fn (InboundMail $record): array => $record->attachmentLabels())
                            ->badge()
                            ->placeholder(__('None'))
                            ->visible(fn (InboundMail $record): bool => $record->attachmentLabels() !== []),
                        TextEntry::make('calendar')
                            ->label(__('Calendar'))
                            ->state(fn (InboundMail $record): ?string => $record->rawCalendar)
                            ->limit(null)
                            ->wrap()
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->visible(fn (InboundMail $record): bool => filled($record->rawCalendar))
                            ->columnSpanFull(),
                        TextEntry::make('diagnostic_json')
                            ->label(__('Payload'))
                            ->state(fn (InboundMail $record): string => $record->diagnosticJson())
                            ->limit(null)
                            ->wrap()
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function resourceUrl(InboundMail $record): ?string
    {
        $viewer = auth()->user();

        return InboundMailResourceLink::url(
            $record->resource,
            $viewer instanceof User ? $viewer : null,
        );
    }
}
