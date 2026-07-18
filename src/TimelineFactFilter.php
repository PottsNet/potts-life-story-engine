<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Illuminate\Support\Collection;

/**
 * Single authority for separating real timeline evidence from structural
 * GEDCOM relationship and housekeeping records.
 */
final class TimelineFactFilter
{
    /** @var array<string,bool> */
    private const STRUCTURAL_TAGS = [
        'NAME' => true,
        'SEX'  => true,
        'FAMC' => true,
        'FAMS' => true,
        'HUSB' => true,
        'WIFE' => true,
        'CHIL' => true,
        'SUBM' => true,
        'SUBN' => true,
        'SOUR' => true,
        'NOTE' => true,
        'RIN'  => true,
        'REFN' => true,
        'CHAN' => true,
        'UID'  => true,
        '_UID' => true,
        '_TODO' => true,
    ];

    public function isTimelineFact(Fact $fact): bool
    {
        $parts = explode(':', $fact->tag());
        $tag = strtoupper((string) end($parts));

        return !isset(self::STRUCTURAL_TAGS[$tag]);
    }

    /**
     * @param Collection<int,Fact> $facts
     * @return Collection<int,Fact>
     */
    public function filter(Collection $facts): Collection
    {
        return $facts
            ->filter(fn (mixed $fact): bool => $fact instanceof Fact && $this->isTimelineFact($fact))
            ->values();
    }
}
