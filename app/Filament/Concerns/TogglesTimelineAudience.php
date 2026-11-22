<?php

namespace App\Filament\Concerns;

trait TogglesTimelineAudience
{
    public bool $timelineAsMember = false;

    public function toggleTimelineAudience(): void
    {
        $this->timelineAsMember = ! $this->timelineAsMember;

        // Filament caches EmbeddedSchema('timeline'); without flushing, the feed stays
        // one click behind the header action label/colour.
        $this->cacheSchema('timeline', null);
    }

    public function updatedTimelineAsMember(): void
    {
        $this->cacheSchema('timeline', null);
    }
}
