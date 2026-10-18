<?php

namespace App\Enums\Concerns;

trait CoversValues
{
    public function covers(self $required): bool
    {
        if ($this === $required) {
            return true;
        }

        foreach ($this->directlyCovers() as $covered) {
            if ($covered->covers($required)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<static>
     */
    protected function directlyCovers(): array
    {
        return [];
    }
}
