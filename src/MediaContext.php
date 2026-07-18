<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

/**
 * A single, explainable interpretation of a media item's date and age.
 */
final class MediaContext
{
    /**
     * @param list<string> $dateReasons
     */
    public function __construct(
        public readonly ?int $effectiveYear,
        public readonly string $dateSource,
        public readonly int $dateConfidence,
        public readonly ?int $ageAtMedia,
        public readonly array $dateReasons
    ) {
    }
}
