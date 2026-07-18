<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

final class MediaClassification
{
    public function __construct(
        public readonly bool $isMedia,
        public readonly string $kind = '',
        public readonly string $targetChapter = 'memories',
        public readonly int $confidence = 0,
        public readonly string $matchedTerm = ''
    ) {
    }
}
