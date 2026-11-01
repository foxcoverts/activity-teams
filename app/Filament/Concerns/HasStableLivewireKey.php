<?php

namespace App\Filament\Concerns;

use Livewire\Component;

/**
 * Give an action a stable Livewire key so a re-render removes or inserts its button instead of morphing it into a neighbour. Morphing leaves Alpine's show and hide expression on the wrong button.
 */
trait HasStableLivewireKey
{
    protected function getLivewireKey(): ?string
    {
        $livewire = $this->getLivewire();

        if (! $livewire instanceof Component) {
            return null;
        }

        return "{$livewire->getId()}.actions.{$this->getName()}";
    }
}
