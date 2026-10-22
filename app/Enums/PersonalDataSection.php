<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PersonalDataSection: string implements HasLabel
{
    case ContactDetails = 'contact-details';
    case EmergencyContact = 'emergency-contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::ContactDetails => __('Contact Details'),
            self::EmergencyContact => __('Emergency Contact'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function attributes(): array
    {
        return match ($this) {
            self::ContactDetails => ['email', 'phone'],
            self::EmergencyContact => ['emergency_name', 'emergency_phone'],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function protectedAttributes(): array
    {
        return array_merge(...array_map(fn (self $section): array => $section->attributes(), self::cases()));
    }
}
