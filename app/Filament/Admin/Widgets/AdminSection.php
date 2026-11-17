<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\AdminAttention;
use Filament\Widgets\Widget;
use Throwable;

/**
 * An admin dashboard section. Loads its own data and isolates failures.
 */
abstract class AdminSection extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    abstract protected function sectionData(): array;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        try {
            return ['failed' => false, ...$this->sectionData()];
        } catch (Throwable $e) {
            report($e);

            return ['failed' => true];
        }
    }

    protected function attention(): AdminAttention
    {
        $user = auth()->user();
        assert($user instanceof User);

        return AdminAttention::for($user);
    }

    protected function viewer(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
