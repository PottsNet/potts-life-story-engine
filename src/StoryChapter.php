<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Illuminate\Support\Collection;

final class StoryChapter
{
    /**
     * @param Collection<int,StoryEvent|StoryGroup> $items
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $stageLabel,
        public readonly string $subtitle,
        public readonly string $narrative,
        public readonly string $transition,
        public readonly Collection $items
    ) {
    }

    public function eventCount(): int
    {
        return $this->items->sum(static function (StoryEvent|StoryGroup $item): int {
            return $item instanceof StoryGroup ? $item->count() : 1;
        });
    }
}
