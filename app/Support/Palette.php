<?php

namespace App\Support;

use Filament\Support\Colors\Color;

final class Palette
{
    /**
     * For information.
     *
     * @return array<int, string>
     */
    public static function informative(): array
    {
        return Color::Blue;
    }

    /**
     * A yes: confirmed, can help.
     *
     * @return array<int, string>
     */
    public static function positive(): array
    {
        return Color::Green;
    }

    /**
     * A no, or a removal: cancelled, cannot make it.
     *
     * @return array<int, string>
     */
    public static function destructive(): array
    {
        return Color::Red;
    }

    /**
     * Not yet sure: tentative, maybe.
     *
     * @return array<int, string>
     */
    public static function uncertain(): array
    {
        return Color::Amber;
    }

    /**
     * Present, with nothing decided yet: awaiting response.
     *
     * @return array<int, string>
     */
    public static function neutral(): array
    {
        return Color::Gray;
    }

    /**
     * The person running a team. Same sky as the manage panel.
     *
     * @return array<int, string>
     */
    public static function manager(): array
    {
        return Color::Sky;
    }

    /**
     * A safeguarding badge for people under 18.
     *
     * @return array<int, string>
     */
    public static function youth(): array
    {
        return Color::Rose;
    }

    /**
     * A safeguarding badge for adults.
     *
     * @return array<int, string>
     */
    public static function adult(): array
    {
        return Color::Indigo;
    }
}
