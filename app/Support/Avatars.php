<?php

namespace App\Support;

use App\Enums\BrandColor;
use App\Models\Team;
use App\Models\User;

final class Avatars
{
    public static function userUrl(User $user, int $size = 200): string
    {
        $hash = hash('sha256', strtolower(trim((string) $user->email)));
        $name = (string) $user->name;
        $background = BrandColor::fromSeed($name)->hex();

        return 'https://www.gravatar.com/avatar/'.$hash.'?'.http_build_query([
            // Path-style ui-avatars URL: Gravatar ignores query strings on custom defaults.
            'd' => self::initialsPathUrl($name, $background, $size, rounded: true),
            's' => $size,
        ]);
    }

    public static function teamUrl(Team $team, int $size = 128): string
    {
        return self::initialsQueryUrl(
            name: (string) $team->name,
            background: $team->brandColor()->hex(),
            size: $size,
            rounded: false,
        );
    }

    public static function brandSwatchUrl(BrandColor $color, int $size = 64): string
    {
        return self::initialsQueryUrl(
            name: $color->getLabel(),
            background: $color->hex(),
            size: $size,
            rounded: false,
        );
    }

    /**
     * Direct ui-avatars URL (fine for <img src>).
     */
    public static function initialsQueryUrl(string $name, string $background, int $size = 128, bool $rounded = false): string
    {
        return 'https://ui-avatars.com/api/?'.http_build_query([
            'name' => $name !== '' ? $name : '?',
            'background' => $background,
            'color' => 'fff',
            'size' => $size,
            'rounded' => $rounded ? 'true' : 'false',
        ]);
    }

    /**
     * Query-free ui-avatars URL for use as a Gravatar custom default.
     *
     * @see https://ui-avatars.com/
     */
    public static function initialsPathUrl(string $name, string $background, int $size = 128, bool $rounded = false): string
    {
        $segments = [
            rawurlencode($name !== '' ? $name : '?'),
            (string) $size,
            $background,
            'fff',
            '2',
            '0.5',
            $rounded ? '1' : '0',
            '1',
            '0',
            'png',
        ];

        return 'https://ui-avatars.com/api/'.implode('/', $segments);
    }
}
