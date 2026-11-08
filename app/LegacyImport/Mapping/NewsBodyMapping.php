<?php

namespace App\LegacyImport\Mapping;

final class NewsBodyMapping
{
    /**
     * Legacy site paths that appear in news markdown → Activity Teams paths.
     *
     * @var array<string, string>
     */
    private const PATHS = [
        '/rota' => '/bookings?tab=rota',
        '/invite' => '/bookings?tab=invites',
    ];

    /**
     * Rewrite internal legacy links in a news markdown body.
     */
    public static function rewrite(string $body): string
    {
        return (string) preg_replace_callback(
            '#\]\((https?://climbfoxcoverts\.co\.uk)?(/[^)\s]*)\)#',
            static function (array $matches): string {
                $path = $matches[2];
                $mapped = self::PATHS[$path] ?? self::PATHS[rtrim($path, '/')] ?? null;

                if ($mapped === null) {
                    return $matches[0];
                }

                return ']('.$mapped.')';
            },
            $body,
        );
    }
}
