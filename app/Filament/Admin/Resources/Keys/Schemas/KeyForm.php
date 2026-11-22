<?php

namespace App\Filament\Admin\Resources\Keys\Schemas;

use App\Filament\Support\Forms\PersonSelect;
use App\Models\Key;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class KeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Key'))
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                    ]),
                Section::make(__('Holder'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        PersonSelect::make('holder_id')
                            ->hiddenLabel()
                            ->relationship(
                                'holder',
                                'name',
                                function (Builder $query, ?Key $record): Builder {
                                    return $query
                                        ->withTrashed()
                                        ->where(function (Builder $holders) use ($record): void {
                                            $holders->whereNull($holders->getModel()->getQualifiedDeletedAtColumn());

                                            if ($record instanceof Key && filled($record->holder_id)) {
                                                $holders->orWhereKey($record->holder_id);
                                            }
                                        });
                                },
                            )
                            ->preload()
                            ->placeholder(__('In the cupboard'))
                            ->nullable()
                            ->rule(fn (?Key $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (blank($value)) {
                                    return;
                                }

                                if ($record instanceof Key && (string) $record->holder_id === (string) $value) {
                                    return;
                                }

                                if (User::query()->whereKey($value)->doesntExist()) {
                                    $fail(User::leftRecordMessage());
                                }
                            }),
                    ]),
                Section::make(__('Unlocks'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('locks')
                            ->hiddenLabel()
                            ->relationship('locks', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
