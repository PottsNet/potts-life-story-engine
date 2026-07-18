<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

final class StoryProfile
{
    /** @param list<string> $chapterOrder */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $chapterOrder
    ) {
    }
}
