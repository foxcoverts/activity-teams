<?php

namespace App\Models\Concerns;

trait HasSequence
{
    abstract protected function sequenced(): array|false;

    public static function bootHasSequence(): void
    {
        static::saving(function ($model) {
            if ($model->usesSequence()) {
                $model->updateSequence();
            }
        });
    }

    public function updateSequence(): static
    {
        $sequenceColumn = $this->getSequenceColumn();

        if (! is_null($sequenceColumn) && ! $this->isDirty($sequenceColumn)) {
            if (! $this->exists) {
                $this->{$sequenceColumn} = $this->freshSequence();
            } elseif ($this->isDirty($this->sequenced())) {
                $this->{$sequenceColumn}++;
            }
        }

        return $this;
    }

    public function freshSequence(): int
    {
        return 0;
    }

    public function usesSequence(): bool
    {
        return $this->sequenced() !== false;
    }

    public function getSequenceColumn(): ?string
    {
        return 'sequence';
    }
}
