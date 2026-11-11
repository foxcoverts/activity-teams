<?php

namespace App\Support;

use App\Models\Task;

class TaskChangeSummary
{
    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        private Task $task,
        private array $changes,
    ) {}

    public function summary(): ?string
    {
        $labels = $this->labels();

        if ($labels === []) {
            return null;
        }

        return collect($labels)->join(', ', ' and ');
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ([
            'summary' => __('Summary'),
            'description' => __('Description'),
            'venue_id' => __('Venue'),
            'due_at' => __('Due'),
            'priority' => __('Priority'),
            'status' => __('Status'),
        ] as $attribute => $label) {
            if ($this->has($attribute)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function has(string $attribute): bool
    {
        return in_array($attribute, $this->changes, true);
    }

    public function suffix(string $attribute): string
    {
        return $this->has($attribute) ? ' '.__('(changed)') : '';
    }
}
