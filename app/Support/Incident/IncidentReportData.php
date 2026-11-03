<?php

namespace App\Support\Incident;

use App\Enums\Incident\Gender;
use App\Enums\Incident\Injury;
use App\Enums\Incident\MembershipType;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Address;
use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;

final readonly class IncidentReportData
{
    /**
     * @param  list<Injury>  $injuries
     */
    public function __construct(
        public string $teamName,
        public CarbonImmutable $dateTime,
        public string $locationName,
        public string $locationDescription,
        public bool $injured,
        public ?string $injuredName,
        public ?CarbonImmutable $injuredDateOfBirth,
        public ?Gender $injuredGender,
        public MembershipType $membershipType,
        public ?string $groupName,
        public string $contactName,
        public ?string $contactPhone,
        public ?string $contactAddress,
        public array $injuries,
        public ?bool $emergencyServices,
        public ?bool $hospital,
        public bool $damaged,
        public string $details,
        public ?string $firstAid,
        public string $reporterName,
        public string $reporterEmail,
        public string $reporterPhone,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data, string $teamName): self
    {
        $membershipType = MembershipType::from((string) $data['membership_type']);
        $injured = self::yesNo($data['injured']);
        $dateTime = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i',
            $data['date'].' '.$data['time'],
            (string) config('app.timezone'),
        );

        if ($dateTime === false) {
            throw new InvalidArgumentException('The incident date and time could not be parsed.');
        }

        $injuredDateOfBirth = $injured && filled($data['injured_dob'] ?? null)
            ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $data['injured_dob'], (string) config('app.timezone'))
            : null;

        if ($injured && filled($data['injured_dob'] ?? null) && $injuredDateOfBirth === false) {
            throw new InvalidArgumentException('The injured person date of birth could not be parsed.');
        }

        return new self(
            teamName: $teamName,
            dateTime: $dateTime,
            locationName: (string) $data['location_name'],
            locationDescription: (string) $data['location_description'],
            injured: $injured,
            injuredName: $injured ? self::nullableString($data['injured_name'] ?? null) : null,
            injuredDateOfBirth: $injuredDateOfBirth instanceof CarbonImmutable ? $injuredDateOfBirth : null,
            injuredGender: $injured ? self::gender($data['injured_gender'] ?? null) : null,
            membershipType: $membershipType,
            groupName: $membershipType->isPublic() ? null : self::nullableString($data['group_name'] ?? null),
            contactName: (string) $data['contact_name'],
            contactPhone: $membershipType->isPublic() ? self::phoneNumber($data['contact_phone'] ?? null) : null,
            contactAddress: $membershipType->isPublic() ? self::nullableString($data['contact_address'] ?? null) : null,
            injuries: $injured
                ? array_map(fn (mixed $injury): Injury => self::injury($injury), $data['injuries'] ?? [])
                : [],
            emergencyServices: $injured ? self::yesNo($data['emergency_services']) : null,
            hospital: $injured ? self::yesNo($data['hospital']) : null,
            damaged: self::yesNo($data['damaged']),
            details: (string) $data['details'],
            firstAid: $injured ? self::nullableString($data['first_aid'] ?? null) : null,
            reporterName: (string) $data['reporter_name'],
            reporterEmail: (string) $data['reporter_email'],
            reporterPhone: self::phoneNumber($data['reporter_phone']) ?? throw new InvalidArgumentException('A reporter phone number is required.'),
        );
    }

    public function hasInjuredName(): bool
    {
        return filled($this->injuredName);
    }

    public function hasInjuredDateOfBirth(): bool
    {
        return $this->injuredDateOfBirth !== null;
    }

    public function hasInjuredGender(): bool
    {
        return $this->injuredGender !== null;
    }

    public function hasGroupName(): bool
    {
        return filled($this->groupName);
    }

    public function hasContactPhone(): bool
    {
        return filled($this->contactPhone);
    }

    public function hasContactAddress(): bool
    {
        return filled($this->contactAddress);
    }

    public function hasInjuries(): bool
    {
        return $this->injuries !== [];
    }

    public function hasEmergencyServices(): bool
    {
        return $this->emergencyServices !== null;
    }

    public function hasHospital(): bool
    {
        return $this->hospital !== null;
    }

    public function hasFirstAid(): bool
    {
        return filled($this->firstAid);
    }

    public function isRIDDOR(): bool
    {
        return $this->hospital === true
            || collect($this->injuries)->contains(fn (Injury $injury): bool => $injury !== Injury::Minor);
    }

    public function reporterMailAddress(): Address
    {
        return new Address($this->reporterEmail, $this->reporterName);
    }

    private static function yesNo(mixed $value): bool
    {
        return match ($value) {
            'yes', true, 1, '1' => true,
            'no', false, 0, '0' => false,
            default => throw new InvalidArgumentException('Incident report yes/no values must be explicit.'),
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        return filled($value) ? (string) $value : null;
    }

    private static function gender(mixed $value): ?Gender
    {
        if (blank($value)) {
            return null;
        }

        return $value instanceof Gender ? $value : Gender::from((string) $value);
    }

    private static function injury(mixed $value): Injury
    {
        return $value instanceof Injury ? $value : Injury::from((string) $value);
    }

    private static function phoneNumber(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return (new PhoneNumber((string) $value, 'GB'))->formatE164();
    }
}
