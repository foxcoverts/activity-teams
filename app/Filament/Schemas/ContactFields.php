<?php

namespace App\Filament\Schemas;

use App\Models\User;
use App\Support\Palette;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;

class ContactFields
{
    public static function phoneInput(string $name): PhoneInput
    {
        return PhoneInput::make($name)
            ->defaultCountry('GB')
            ->initialCountry('GB')
            ->disableLookup()
            ->displayNumberFormat(PhoneInputNumberType::INTERNATIONAL)
            ->inputNumberFormat(PhoneInputNumberType::E164);
    }

    public static function phoneEntry(string $name): PhoneEntry
    {
        return PhoneEntry::make($name)
            ->defaultCountry('GB')
            ->displayFormat(PhoneInputNumberType::INTERNATIONAL)
            ->placeholder('-');
    }

    public static function phoneNoticeCopy(): string
    {
        return __('Your phone number is shown to the lead instructor and to other people attending the same booking, until the end of that booking. Only share it if you are happy for that.');
    }

    public static function emergencyNoticeCopy(): string
    {
        return __('Your emergency contact is shown only to people attending a booking you are attending, on the day of the booking, so they can reach someone if you cannot. Only give details of someone who knows you are sharing them.');
    }

    public static function phoneNotice(): Text
    {
        return Text::make(static::phoneNoticeCopy());
    }

    public static function emergencyNotice(): Text
    {
        return Text::make(static::emergencyNoticeCopy());
    }

    public static function privacyNoticeLink(): Text
    {
        $url = e((string) config('app.privacy_notice_url'));
        $label = e(__('Privacy notice'));

        return Text::make(new HtmlString('<a class="underline" href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$label.'</a>'));
    }

    public static function privacyNoticeAcknowledgement(): Checkbox
    {
        return Checkbox::make('privacy_notice_accepted')
            ->label(__('I have read the privacy notice'))
            ->accepted()
            ->dehydrated(false)
            ->default(false);
    }

    public static function emailVerifiedHint(User $user): array
    {
        $verified = $user->hasVerifiedEmail();

        return [
            'icon' => $verified ? Heroicon::CheckCircle : Heroicon::ExclamationCircle,
            'color' => $verified ? Palette::positive() : Palette::uncertain(),
            'hint' => $verified ? __('Email verified') : __('Email not verified'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function emergencyFields(): array
    {
        return [
            static::emergencyNotice(),
            TextInput::make('emergency_name')
                ->maxLength(100),
            static::phoneInput('emergency_phone')
                ->label(__('Emergency contact phone')),
        ];
    }
}
