<?php

namespace App\Filament\Schemas;

use App\Enums\PersonalDataSection as Section;
use App\Models\User;
use App\Support\Palette;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section as SectionComponent;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

class PersonalDataSection
{
    /**
     * Wrap infolist entries so they are only rendered once the viewer has
     * acknowledged the notice for this person. Until then, only the notice is shown.
     *
     * @param  array<int, mixed>  $entries
     */
    public static function make(Section $section, array $entries): SectionComponent
    {
        $isAcknowledged = fn (User $record): bool => auth()->user()?->hasAcknowledgedPersonalData($record, $section) ?? false;

        return SectionComponent::make($section->getLabel())
            ->collapsible()
            ->collapsed()
            ->afterHeader($section === Section::ContactDetails ? [static::contactDetailsBadge()] : null)
            ->schema([
                ViewEntry::make('acknowledge-'.$section->value)
                    ->hiddenLabel()
                    ->view('filament.bookings.personal-data-acknowledgement', ['section' => $section])
                    ->visible(fn (User $record): bool => ! $isAcknowledged($record)),
                Group::make($entries)
                    ->visible($isAcknowledged),
            ]);
    }

    /**
     * Wrap form fields so they are only rendered, and therefore only submitted,
     * once the viewer has acknowledged the notice for this person. New records have no one to acknowledge.
     *
     * @param  array<int, mixed>  $fields
     */
    public static function form(Section $section, array $fields): SectionComponent
    {
        $isAcknowledged = fn (?User $record): bool => $record === null
            || (auth()->user()?->hasAcknowledgedPersonalData($record, $section) ?? false);

        return SectionComponent::make($section->getLabel())
            ->collapsible()
            ->collapsed(fn (?User $record): bool => $record !== null)
            ->afterHeader($section === Section::ContactDetails ? [static::contactDetailsBadge()] : null)
            ->schema([
                View::make('filament.bookings.personal-data-acknowledgement')
                    ->viewData(['section' => $section])
                    ->visible(fn (?User $record): bool => ! $isAcknowledged($record)),
                Group::make($fields)
                    ->visible($isAcknowledged),
            ]);
    }

    /**
     * Account email (editable) or invited email (read-only) for Contact Details forms.
     *
     * @return array{0: TextInput, 1: Placeholder}
     */
    public static function emailFields(?string $accountLabel = null): array
    {
        return [
            TextInput::make('email')
                ->label($accountLabel ?? __('Email'))
                ->email()
                ->required()
                ->visible(fn (?User $record): bool => $record === null || ! $record->isPlaceholder()),
            Placeholder::make('invited_email')
                ->label(__('Invited email'))
                ->content(fn (?User $record): ?string => $record?->invitation?->email)
                ->visible(fn (?User $record): bool => $record?->isPlaceholder() === true
                    && filled($record->invitation?->email)),
        ];
    }

    public static function emailEntry(?string $accountLabel = null): TextEntry
    {
        return TextEntry::make('email')
            ->label(fn (User $record): string => $record->isPlaceholder()
                ? __('Invited email')
                : ($accountLabel ?? __('Email')))
            ->state(fn (User $record): ?string => $record->isPlaceholder()
                ? $record->invitation?->email
                : $record->email)
            ->visible(fn (User $record): bool => $record->isPlaceholder()
                ? filled($record->invitation?->email)
                : true);
    }

    protected static function contactDetailsBadge(): Text
    {
        return Text::make(fn (?User $record): string => match (true) {
            $record === null => '',
            $record->isPlaceholder() && $record->invitation === null => __('No invitation'),
            $record->isPlaceholder() => $record->invitationStatus()->getLabel(),
            $record->hasVerifiedEmail() => __('Email verified'),
            default => __('Email not verified'),
        })
            ->badge()
            ->size(Size::Small)
            ->visible(fn (?User $record): bool => $record !== null)
            ->icon(fn (?User $record): Heroicon => match (true) {
                $record === null => Heroicon::ExclamationCircle,
                $record->isPlaceholder() && $record->invitation === null => Heroicon::ExclamationTriangle,
                $record->isPlaceholder() => $record->invitationStatus()->getIcon(),
                $record->hasVerifiedEmail() => Heroicon::CheckCircle,
                default => Heroicon::ExclamationCircle,
            })
            ->color(fn (?User $record): array => match (true) {
                $record === null => Palette::uncertain(),
                $record->isPlaceholder() && $record->invitation === null => Palette::uncertain(),
                $record->isPlaceholder() => $record->invitationStatus()->getColor(),
                $record->hasVerifiedEmail() => Palette::positive(),
                default => Palette::uncertain(),
            });
    }
}
