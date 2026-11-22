<?php

namespace App\Filament\Keys;

use App\Actions\PassKeyAction as PassKey;
use App\Filament\Member\Resources\Keys\KeyResource as MemberKeyResource;
use App\Filament\Support\Forms\PersonSelect;
use App\Filament\Support\Person;
use App\Models\Key;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class PassKeyAction
{
    public static function make(): Action
    {
        return Action::make('pass')
            ->label(__('Pass'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading(__('Pass key'))
            ->modalIcon(Heroicon::OutlinedKey)
            ->authorize(fn (Key $record): bool => auth()->user()?->can('pass', $record) ?? false)
            ->visible(fn (Key $record): bool => static::canShow($record))
            ->schema([
                PersonSelect::make('holder_id')
                    ->label(__('Pass to'))
                    ->options(fn (Key $record): array => $record->passOptions())
                    ->getOptionLabelUsing(function (?string $value): ?string {
                        if ($value === Key::CUPBOARD) {
                            return __('In the cupboard');
                        }

                        $person = Person::resolveId($value);

                        return $person instanceof User
                            ? Person::optionLabel($person)
                            : null;
                    })
                    ->required()
                    ->selectablePlaceholder(false),
            ])
            ->action(function (Key $record, array $data): void {
                $user = auth()->user();
                abort_unless($user instanceof User, 403);

                $holderId = ($data['holder_id'] ?? null) === Key::CUPBOARD
                    ? null
                    : (string) $data['holder_id'];

                (new PassKey)($user, $record, $holderId);
            })
            ->successRedirectUrl(fn (): ?string => Filament::getCurrentPanel()?->getId() === 'member'
                ? MemberKeyResource::getUrl('index')
                : null);
    }

    private static function canShow(Key $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('pass', $record)
            && $record->hasPassDestination();
    }
}
