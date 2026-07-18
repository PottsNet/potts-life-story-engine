<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Media;

final class StoryEvent
{
    public function __construct(
        public readonly Fact $fact,
        public readonly string $chapter,
        public readonly string $importance,
        public readonly string $type,
        public readonly string $icon,
        public readonly string $accent,
        public readonly int $score,
        public readonly bool $isMedia = false,
        public readonly string $mediaKind = '',
        public readonly string $mediaTargetChapter = '',
        public readonly int $mediaConfidence = 0,
        public readonly ?Media $media = null,
        public readonly ?int $mediaEffectiveYear = null,
        /** @var list<string> */
        public readonly array $mediaPlacementReasons = [],
        /** @var array<string,int> */
        public readonly array $mediaPlacementScores = [],
        public readonly ?int $mediaAge = null,
        public readonly string $mediaMatchedEventLabel = '',
        public readonly ?int $mediaMatchedEventYear = null,
        public readonly string $mediaDateSource = 'unknown',
        public readonly int $mediaDateConfidence = 0,
        /** @var list<StoryNote> */
        public readonly array $notes = []
    ) {
    }
}
