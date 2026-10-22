<?php

namespace App\Support;

use Filament\Support\Colors\Color;

final class Palette
{
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
     * A young person's section: Squirrels through Network.
     *
     * @return array<int, string>
     */
    public static function youth(): array
    {
        return Color::Teal;
    }

    /**
     * An adult's section, distinct from the youth sections.
     *
     * @return array<int, string>
     */
    public static function adult(): array
    {
        return Color::Indigo;
    }
}
