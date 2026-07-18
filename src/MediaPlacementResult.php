<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

/**
 * Immutable result from the weighted media-placement engine.
 * Scores and reasons are retained for future administrator diagnostics.
 */
final class MediaPlacementResult
{
    /**
     * @param array<string,int> $scores
     * @param list<string>      $reasons
     */
    public function __construct(
        public readonly string $chapter,
        public readonly int $confidence,
        public readonly ?int $effectiveYear,
        public readonly array $scores,
        public readonly array $reasons,
        public readonly ?int $ageAtMedia = null,
        public readonly string $matchedEventLabel = '',
        public readonly ?int $matchedEventYear = null,
        public readonly string $dateSource = 'unknown',
        public readonly int $dateConfidence = 0
    ) {
    }
}
