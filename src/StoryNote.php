<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

final class StoryNote
{
    public function __construct(
        public readonly string $gedcom,
        public readonly string $plainText,
        public readonly bool $isLong,
        public readonly bool $isLinked
    ) {
    }
}
