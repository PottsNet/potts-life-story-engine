<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Illuminate\Support\Collection;

final class StoryGroup
{
    /**
     * @param Collection<int,StoryEvent> $events
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $icon,
        public readonly string $accent,
        public readonly Collection $events,
        public readonly ?StoryEvent $featuredEvent = null
    ) {
    }

    public function count(): int
    {
        return $this->events->count();
    }
}
