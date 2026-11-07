<?php

namespace App\Filament\Schemas;

use App\Enums\PersonalDataSection as Section;
use App\Models\User;
use App\Support\Palette;
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
            ->afterHeader($section === Section::ContactDetails ? [static::emailVerifiedBadge()] : null)
            ->schema([
                ViewEntry::make('acknowledge-'.$section->value)
                    ->hiddenLabel()
                    ->view('filament.bookings.personal-data-acknowledgement', ['section' => $section])
                    ->visible(fn (User $record): bool => ! $isAcknowledged($record)),
                Group::make($entries)
                    ->visible($isAcknowledged),
            ]);
    }

    protected static function emailVerifiedBadge(): Text
    {
        return Text::make(fn (?User $record): string => $record?->hasVerifiedEmail() ? __('Email verified') : __('Email not verified'))
            ->badge()
            ->size(Size::Small)
            ->visible(fn (?User $record): bool => $record !== null)
            ->icon(fn (?User $record): Heroicon => $record?->hasVerifiedEmail() ? Heroicon::CheckCircle : Heroicon::ExclamationCircle)
            ->color(fn (?User $record): array => $record?->hasVerifiedEmail() ? Palette::positive() : Palette::uncertain());
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
            ->afterHeader($section === Section::ContactDetails ? [static::emailVerifiedBadge()] : null)
            ->schema([
                View::make('filament.bookings.personal-data-acknowledgement')
                    ->viewData(['section' => $section])
                    ->visible(fn (?User $record): bool => ! $isAcknowledged($record)),
                Group::make($fields)
                    ->visible($isAcknowledged),
            ]);
    }
}
