<?php

namespace App\Filament\KeyRings;

use App\Actions\PassKeyRingAction as PassKeyRing;
use App\Filament\Member\Resources\KeyRings\KeyRingResource as MemberKeyRingResource;
use App\Models\KeyRing;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class PassKeyRingAction
{
    public static function make(): Action
    {
        return Action::make('pass')
            ->label(__('Pass'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading(__('Pass key ring'))
            ->authorize(fn (KeyRing $record): bool => auth()->user()?->can('pass', $record) ?? false)
            ->visible(fn (KeyRing $record): bool => static::canShow($record))
            ->schema([
                Select::make('holder_id')
                    ->label(__('Pass to'))
                    ->options(fn (KeyRing $record): array => $record->passOptions())
                    ->searchable()
                    ->required()
                    ->selectablePlaceholder(false),
            ])
            ->action(function (KeyRing $record, array $data): void {
                $user = auth()->user();
                abort_unless($user instanceof User, 403);

                $holderId = ($data['holder_id'] ?? null) === KeyRing::CUPBOARD
                    ? null
                    : (string) $data['holder_id'];

                (new PassKeyRing)($user, $record, $holderId);
            })
            ->successRedirectUrl(fn (): ?string => Filament::getCurrentPanel()?->getId() === 'member'
                ? MemberKeyRingResource::getUrl('index')
                : null);
    }

    private static function canShow(KeyRing $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('pass', $record)
            && $record->hasPassDestination();
    }
}
