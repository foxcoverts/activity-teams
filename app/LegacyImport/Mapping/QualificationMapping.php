<?php

namespace App\LegacyImport\Mapping;

final class QualificationMapping
{
    /**
     * @var array<string, string>
     */
    private const MOUNTAIN_TRAINING_NAMES = [
        'ICA' => 'Indoor Climbing Assistant',
        'CWI' => 'Climbing Wall Instructor',
        'CWI Abseil' => 'Climbing Wall Instructor (Abseil)',
        'CWDI' => 'Climbing Wall Development Instructor',
        'RCI' => 'Rock Climbing Instructor',
        'RCDI' => 'Rock Climbing Development Instructor',
        'ML' => 'Mountain Leader',
        'MCI' => 'Mountaineering and Climbing Instructor',
        'WMCI' => 'Winter Mountaineering and Climbing Instructor',
    ];

    /**
     * @var array<string, string>
     */
    private const DETAIL_KINDS = [
        'App\\Models\\ScoutPermit' => 'scout_permit',
        'App\\Models\\MountainTrainingQualification' => 'mountain_training',
        'App\\Models\\GirlguidingQualification' => 'girlguiding',
    ];

    public static function kind(string $detailType): ?string
    {
        return self::DETAIL_KINDS[$detailType] ?? null;
    }

    /**
     * @return array{kind: string, payload: array<string, mixed>, expires_on: mixed}|null
     */
    public static function fromDetail(string $detailType, object $detail, mixed $expiresOn): ?array
    {
        $kind = self::kind($detailType);

        if ($kind === null) {
            return null;
        }

        return match ($kind) {
            'scout_permit' => [
                'kind' => 'scout_permit',
                'payload' => array_filter([
                    'activity' => $detail->activity,
                    'category' => $detail->category,
                    'permit_type' => $detail->permit_type,
                    'restrictions' => $detail->restrictions,
                ], fn ($value) => $value !== null && $value !== ''),
                'expires_on' => $expiresOn,
            ],
            'mountain_training' => [
                'kind' => 'mountain_training',
                'payload' => [
                    'award' => self::MOUNTAIN_TRAINING_NAMES[$detail->award] ?? $detail->award,
                ],
                'expires_on' => null,
            ],
            'girlguiding' => [
                'kind' => 'girlguiding',
                'payload' => [
                    'scheme' => $detail->scheme === 'climbing' ? 'Climbing' : $detail->scheme,
                    'level' => (string) $detail->level,
                ],
                'expires_on' => $expiresOn,
            ],
            default => null,
        };
    }
}
