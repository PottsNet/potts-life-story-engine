<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Illuminate\Support\Collection;

final class LifeStory
{
    /**
     * @param Collection<string,StoryChapter> $chapters
     * @param list<array{year:string,label:string,chapter:string}> $milestones
     * @param list<string> $highlights
     * @param list<array{year:string,title:string,summary:string,chapter:string,accent:string}> $turningPoints
     * @param list<HistoricalContextItem> $historicalContext
     */
    public function __construct(
        public readonly Collection $chapters,
        public readonly string $introduction = '',
        public readonly array $milestones = [],
        public readonly array $highlights = [],
        public readonly array $turningPoints = [],
        public readonly array $historicalContext = [],
        public readonly string $coverQuote = "",
        public readonly ?StoryProfile $profile = null
    ) {
    }

    public function eventCount(): int
    {
        return $this->chapters->sum(static fn (StoryChapter $chapter): int => $chapter->eventCount());
    }
}
