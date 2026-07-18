<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

final class HistoricalContextItem
{
    public function __construct(
        public readonly string $date,
        public readonly string $text,
        public readonly string $category = '',
        public readonly string $url = '',
        public readonly ?int $age = null,
        public readonly string $relatedChapter = '',
        public readonly string $countryCode = '',
        public readonly string $countryName = ''
    ) {
    }
}
