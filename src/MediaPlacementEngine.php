<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;

/**
 * Weighted chapter placement for media with timeline intelligence.
 *
 * Explicit GEDCOM links remain authoritative. Standalone media is scored from
 * its type, wording, effective date and nearby dated life events. Weak or
 * conflicting evidence falls back to Photographs and keepsakes.
 */
final class MediaPlacementEngine
{
    public function __construct(
        private readonly TimelineFactFilter $timelineFactFilter,
        private readonly MediaContextResolver $mediaContextResolver
    ) {
    }

    /** @var list<string> */
    private const CHAPTERS = ['origins', 'learning', 'journey', 'family', 'career', 'places', 'community', 'final', 'memories'];

    /** @var array<string,string> */
    private const EVENT_CHAPTERS = [
        'BIRT' => 'origins', 'CHR' => 'origins', 'BAPM' => 'origins', 'ADOP' => 'origins',
        'EDUC' => 'learning', 'GRAD' => 'learning',
        'IMMI' => 'journey', 'EMIG' => 'journey', 'NATU' => 'journey',
        'MARR' => 'family', 'ENGA' => 'family', 'DIV' => 'family', 'ANUL' => 'family',
        'OCCU' => 'career', 'RETI' => 'career',
        'RESI' => 'places', 'CENS' => 'places', 'PROP' => 'places',
        'MILI' => 'community', '_MILT' => 'community', 'RELI' => 'community', 'TITL' => 'community',
        'WILL' => 'final', 'PROB' => 'final', 'DEAT' => 'final', 'BURI' => 'final', 'CREM' => 'final',
    ];

    /** @var array<string,bool> */
    private const MAJOR_EVENT_TYPES = [
        'BIRT' => true, 'ADOP' => true, 'IMMI' => true, 'EMIG' => true,
        'MARR' => true, 'DIV' => true, 'MILI' => true, '_MILT' => true,
        'RETI' => true, 'WILL' => true, 'DEAT' => true,
    ];

    /** @var array<string,array<string,int>> */
    private const TERMS = [
        'origins' => [
            'birth certificate' => 90, 'christening certificate' => 90, 'baptism certificate' => 90,
            'baby photo' => 78, 'baby' => 62, 'infant' => 62, 'childhood' => 52,
            'christening' => 72, 'baptism' => 72, 'baptised' => 72, 'baptized' => 72,
        ],
        'family' => [
            'marriage certificate' => 90, 'wedding certificate' => 90, 'wedding' => 78,
            'marriage' => 76, 'bride' => 70, 'groom' => 70, 'anniversary' => 58,
        ],
        'learning' => [
            'school certificate' => 85, 'school report' => 82, 'teachers college' => 78,
            'teacher training' => 78, 'graduation' => 80, 'school class' => 72,
            'class photo' => 72, 'school' => 58, 'college' => 58, 'university' => 58,
        ],
        'career' => [
            'head teacher' => 82, 'staff photo' => 76, 'workplace' => 72, 'employment' => 68,
            'occupation' => 68, 'career' => 68, 'teacher' => 60, 'business' => 58,
            'councillor' => 68, 'mayor' => 68,
        ],
        'community' => [
            'military service' => 90, 'service record' => 86, 'war service' => 84,
            'army' => 76, 'navy' => 76, 'air force' => 76, 'airforce' => 76,
            'soldier' => 72, 'uniform' => 68, 'anzac' => 76, 'salvation army' => 70,
        ],
        'places' => [
            'property title' => 82, 'house deed' => 82, 'homestead' => 72,
            'family home' => 68, 'residence' => 64, 'property' => 62, 'house' => 58, 'farm' => 56,
        ],
        'journey' => [
            'immigration' => 90, 'emigration' => 90, 'migration' => 86, 'immigrant' => 84,
            'passport' => 82, 'arrival' => 72, 'departure' => 72, 'voyage' => 70, 'ship' => 58,
        ],
        'final' => [
            'death certificate' => 90, 'death notice' => 90, 'obituary' => 86, 'funeral' => 84,
            'headstone' => 82, 'grave' => 78, 'cemetery' => 74, 'burial' => 78,
            'probate' => 80, 'last will' => 80, 'in memoriam' => 80,
        ],
    ];

    public function place(
        Individual $individual,
        Fact $fact,
        Media $media,
        MediaClassification $classification,
        ?string $explicitEventChapter
    ): MediaPlacementResult {
        $scores = array_fill_keys(self::CHAPTERS, 0);
        $reasons = [];
        $text = $this->mediaText($fact, $media);
        $mediaOnlyText = $this->mediaOnlyText($media);
        $mediaContext = $this->mediaContextResolver->resolve($individual, $fact, $media, $explicitEventChapter);
        $effectiveYear = $mediaContext->effectiveYear;
        $birthYear = $this->birthYear($individual);
        $deathYear = $this->deathYear($individual);
        $ageAtMedia = $mediaContext->ageAtMedia;
        foreach ($mediaContext->dateReasons as $dateReason) {
            $reasons[] = $dateReason;
        }
        $matchedEventLabel = '';
        $matchedEventYear = null;

        $contextualOverride = $this->contextualEventOverride(
            $individual,
            $explicitEventChapter,
            $mediaOnlyText,
            $effectiveYear,
            $ageAtMedia,
            $classification
        );

        if ($explicitEventChapter !== null && isset($scores[$explicitEventChapter])) {
            $explicitWeight = $contextualOverride !== null ? 45 : 180;
            $scores[$explicitEventChapter] += $explicitWeight;
            $reasons[] = $contextualOverride !== null
                ? 'The GEDCOM link was retained as supporting evidence, but the media describes a different family occasion (+' . $explicitWeight . ' ' . $explicitEventChapter . ')'
                : 'Explicitly linked to the ' . $explicitEventChapter . ' life-event chapter (+180)';
        }

        if ($contextualOverride !== null) {
            $scores[$contextualOverride['chapter']] += $contextualOverride['bonus'];
            $reasons[] = $contextualOverride['reason'] . ' (+' . $contextualOverride['bonus'] . ' ' . $contextualOverride['chapter'] . ')';
        }
        foreach (self::TERMS as $chapter => $terms) {
            foreach ($terms as $term => $weight) {
                if (str_contains($text, $term)) {
                    $scores[$chapter] += $weight;
                    $reasons[] = 'Matched “' . $term . '” for ' . $chapter . ' (+' . $weight . ')';
                    break;
                }
            }
        }

        $memoryBase = match ($classification->kind) {
            'photograph' => 58,
            'letter', 'keepsake' => 54,
            'newspaper', 'certificate', 'document' => 34,
            default => 42,
        };
        $scores['memories'] += $memoryBase;
        $reasons[] = 'Photographs and keepsakes fallback (+' . $memoryBase . ')';

        // Age sanity checks are deliberately applied before timeline matching.
        // A dated adult christening photograph can still join an actual adult
        // christening event, but cannot enter Early years from wording alone.
        if ($effectiveYear !== null && $birthYear !== null) {
            if ($ageAtMedia !== null && $ageAtMedia >= 0 && $ageAtMedia <= 5) {
                $scores['origins'] += 42;
                $reasons[] = 'Media date is within five years of birth (+42 origins)';
            } elseif ($ageAtMedia !== null && $ageAtMedia <= 12) {
                $scores['origins'] += 24;
                $reasons[] = 'Media date falls in childhood (+24 origins)';
            } elseif ($ageAtMedia !== null && $ageAtMedia > 17 && $explicitEventChapter !== 'origins') {
                $scores['origins'] -= 120;
                $reasons[] = 'Media date is from adulthood (-120 origins)';
            }
        }

        if ($effectiveYear !== null && $deathYear !== null && abs($deathYear - $effectiveYear) <= 2) {
            $scores['final'] += 28;
            $reasons[] = 'Media date is close to death year (+28 final)';
        }

        // Timeline intelligence is used only for standalone media. A direct
        // GEDCOM event link remains the strongest and clearest evidence.
        if ($explicitEventChapter === null && $effectiveYear !== null) {
            $match = $this->bestTimelineMatch($individual, $effectiveYear, $scores);
            if ($match !== null) {
                $scores[$match['chapter']] += $match['bonus'];
                $matchedEventLabel = $match['label'];
                $matchedEventYear = $match['year'];
                $reasons[] = 'Media year ' . $effectiveYear . ' is close to ' . $match['label']
                    . ' (' . $match['year'] . ') (+' . $match['bonus'] . ' ' . $match['chapter'] . ')';
            }
        }

        arsort($scores);
        $chapter = (string) array_key_first($scores);
        $topScore = (int) reset($scores);
        $secondScore = (int) (array_values($scores)[1] ?? 0);

        // A strong contextual contradiction must win over the GEDCOM attachment.
        // This handles media such as a child's photograph at their parents'
        // anniversary that has been attached to the child's own marriage fact.
        if ($contextualOverride !== null) {
            $chapter = $contextualOverride['chapter'];
            $topScore = (int) ($scores[$chapter] ?? $topScore);
            $secondScore = max(array_values(array_filter(
                $scores,
                static fn (int $score, string $key): bool => $key !== $chapter,
                ARRAY_FILTER_USE_BOTH
            )) ?: [0]);
            $reasons[] = 'Contextual contradiction overrode the linked event chapter';
        } elseif ($explicitEventChapter === null && ($topScore < 65 || ($topScore - $secondScore) < 12)) {
            $chapter = 'memories';
            $topScore = $scores['memories'];
            $reasons[] = 'Evidence was weak or ambiguous; used the safe keepsakes fallback';
        }

        $margin = max(0, $topScore - $secondScore);
        $confidence = min(100, max(35, 45 + intdiv(max(0, $topScore), 3) + intdiv($margin, 4)));
        if ($contextualOverride !== null) {
            $confidence = 98;
        } elseif ($explicitEventChapter !== null) {
            $confidence = 100;
        }

        return new MediaPlacementResult(
            $chapter,
            $confidence,
            $effectiveYear,
            $scores,
            $reasons,
            $ageAtMedia !== null && $ageAtMedia >= 0 ? $ageAtMedia : null,
            $matchedEventLabel,
            $matchedEventYear,
            $mediaContext->dateSource,
            $mediaContext->dateConfidence
        );
    }

    /**
     * @param array<string,int> $currentScores
     * @return array{chapter:string,label:string,year:int,bonus:int}|null
     */
    private function bestTimelineMatch(Individual $individual, int $mediaYear, array $currentScores): ?array
    {
        $candidates = [];
        $facts = $this->timelineFactFilter->filter(
            $individual->facts([], false, null, true)
        );
        foreach ($individual->spouseFamilies() as $family) {
            if ($family instanceof Family) {
                $facts = $facts->merge($family->facts(['ENGA', 'MARR', 'MARB', 'MARC', 'MARL', 'MARS', 'DIV', 'ANUL'], false, null, true));
            }
        }

        foreach ($facts as $candidate) {
            if (!$candidate instanceof Fact || !$candidate->date()->isOK()) {
                continue;
            }
            $type = $this->factType($candidate);
            $chapter = self::EVENT_CHAPTERS[$type] ?? null;
            if ($chapter === null) {
                continue;
            }
            $year = $candidate->date()->minimumDate()->year();
            if ($year <= 0) {
                continue;
            }
            $distance = abs($mediaYear - $year);
            if ($distance > 3) {
                continue;
            }

            $bonus = match ($distance) {
                0 => isset(self::MAJOR_EVENT_TYPES[$type]) ? 72 : 58,
                1 => isset(self::MAJOR_EVENT_TYPES[$type]) ? 44 : 34,
                2 => isset(self::MAJOR_EVENT_TYPES[$type]) ? 26 : 20,
                default => 12,
            };

            // Where wording already favours a chapter, a nearby matching event
            // is especially persuasive. This helps school, wedding, migration,
            // career and service media without forcing unrelated dated portraits.
            if (($currentScores[$chapter] ?? 0) >= 50) {
                $bonus += 16;
            }

            $candidates[] = [
                'chapter' => $chapter,
                'label' => trim(strip_tags($candidate->label())),
                'year' => $year,
                'bonus' => $bonus,
                'distance' => $distance,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            return [$b['bonus'], -$b['distance']] <=> [$a['bonus'], -$a['distance']];
        });

        $best = $candidates[0];
        $second = $candidates[1] ?? null;

        // Do not make a timeline placement where two different chapters are
        // essentially tied. Ambiguous dated photographs belong in Keepsakes.
        if ($second !== null && $best['chapter'] !== $second['chapter'] && abs($best['bonus'] - $second['bonus']) < 10) {
            return null;
        }

        return [
            'chapter' => $best['chapter'],
            'label' => $best['label'],
            'year' => $best['year'],
            'bonus' => $best['bonus'],
        ];
    }

    private function birthYear(Individual $individual): ?int
    {
        $fact = $individual->facts(['BIRT'], false, null, true)->first();
        if ($fact instanceof Fact && $fact->date()->isOK()) {
            $year = $fact->date()->minimumDate()->year();
            return $year > 0 ? $year : null;
        }
        return null;
    }

    private function deathYear(Individual $individual): ?int
    {
        $fact = $individual->facts(['DEAT'], false, null, true)->first();
        if ($fact instanceof Fact && $fact->date()->isOK()) {
            $year = $fact->date()->minimumDate()->year();
            return $year > 0 ? $year : null;
        }
        return null;
    }

    private function factType(Fact $fact): string
    {
        $parts = explode(':', $fact->tag());
        return (string) end($parts);
    }

    /**
     * Detect media that is attached to one event but clearly tells a different
     * part of the subject's story. This is intentionally conservative: only
     * strong contextual contradictions can soften an explicit GEDCOM link.
     *
     * @return array{chapter:string,bonus:int,reason:string}|null
     */
    private function contextualEventOverride(
        Individual $individual,
        ?string $explicitEventChapter,
        string $text,
        ?int $effectiveYear,
        ?int $ageAtMedia,
        MediaClassification $classification
    ): ?array {
        $isParentsAnniversary = preg_match(
            '/\b(silver wedding|golden wedding|diamond wedding|wedding anniversary|parents? anniversary|mother(?: and| &) father anniversary|family anniversary)\b/u',
            $text
        ) === 1;

        // This rule applies whether the media was linked to a marriage fact or
        // to a generic EVEN/Photo fact.  The previous implementation only ran
        // for explicit family links, allowing words such as "wedding" and
        // "anniversary" to place a childhood family photograph in the
        // subject's own Marriage and family chapter.
        if ($isParentsAnniversary) {
            if ($ageAtMedia !== null && $ageAtMedia >= 0 && $ageAtMedia < 18) {
                return [
                    'chapter' => 'origins',
                    'bonus' => 210,
                    'reason' => 'The title describes an older generation’s wedding anniversary and the subject was still a child',
                ];
            }

            return [
                'chapter' => 'memories',
                'bonus' => 150,
                'reason' => 'The title describes a family anniversary rather than the subject’s own marriage',
            ];
        }

        // A dated childhood family photograph cannot represent the subject's
        // own adult marriage simply because its title contains family/wedding
        // wording.  Apply this even to generic EVEN "Family Photo" records.
        $isFamilyGroupPhoto = preg_match(
            '/\b(family photo|family portrait|family group|group photo|group portrait)\b/u',
            $text
        ) === 1;
        $hasMarriageWording = preg_match(
            '/\b(wedding|marriage|bride|groom|anniversary)\b/u',
            $text
        ) === 1;

        if (
            $classification->kind === 'photograph'
            && $ageAtMedia !== null
            && $ageAtMedia >= 0
            && $ageAtMedia < 16
            && ($isFamilyGroupPhoto || $hasMarriageWording)
        ) {
            return [
                'chapter' => 'origins',
                'bonus' => 220,
                'reason' => 'The photograph dates from childhood and records a wider family occasion, not the subject’s own marriage',
            ];
        }

        // Wedding wording alone is not enough to make a photograph evidence of
        // the subject's own marriage.  If the photograph's reliable date is
        // materially different from every marriage date recorded for this
        // person, treat it as a photograph of another person's wedding or a
        // later family occasion and place it in keepsakes.  This catches titles
        // such as "Brad's wedding" without requiring the researcher to move
        // the underlying GEDCOM link.
        if (
            $classification->kind === 'photograph'
            && $effectiveYear !== null
            && preg_match('/\b(wedding|marriage|bride|groom|nuptial)\b/u', $text) === 1
        ) {
            $marriageYears = $this->marriageYears($individual);
            if ($marriageYears !== []) {
                $nearestMarriageYear = min(array_map(
                    static fn (int $year): int => abs($effectiveYear - $year),
                    $marriageYears
                ));

                if ($nearestMarriageYear > 2) {
                    return [
                        'chapter' => 'memories',
                        'bonus' => 230,
                        'reason' => 'The photograph mentions a wedding, but its date does not match any marriage recorded for the subject',
                    ];
                }
            } elseif ($explicitEventChapter !== 'family') {
                return [
                    'chapter' => 'memories',
                    'bonus' => 180,
                    'reason' => 'Wedding wording was not supported by a marriage event for the subject',
                ];
            }
        }

        // A child cannot be the principal subject of their own marriage event.
        // When the media is photographic and its date places the subject below
        // marriageable age, prefer childhood or keepsakes unless the metadata
        // clearly describes another recognised event.
        if (
            $explicitEventChapter === 'family'
            && $classification->kind === 'photograph'
            && $ageAtMedia !== null
            && $ageAtMedia >= 0
            && $ageAtMedia < 16
        ) {
            return [
                'chapter' => 'origins',
                'bonus' => 175,
                'reason' => 'The dated photograph predates the subject’s adult family life',
            ];
        }

        return null;
    }

    /** @return list<int> */
    private function marriageYears(Individual $individual): array
    {
        $years = [];
        foreach ($individual->spouseFamilies() as $family) {
            if (!$family instanceof Family) {
                continue;
            }
            foreach ($family->facts(['MARR', 'MARB', 'MARC', 'MARL', 'MARS'], false, null, true) as $fact) {
                if ($fact instanceof Fact && $fact->date()->isOK()) {
                    $year = $fact->date()->minimumDate()->year();
                    if ($year > 0) {
                        $years[] = $year;
                    }
                }
            }
        }

        return array_values(array_unique($years));
    }

    private function mediaOnlyText(Media $media): string
    {
        $parts = [$media->fullName(), $media->getNote()];
        foreach ($media->mediaFiles() as $file) {
            $parts[] = $file->filename();
            $parts[] = $file->title();
            $parts[] = $file->type();
            $parts[] = $file->format();
        }

        $text = html_entity_decode(implode(' ', $parts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(strip_tags($text));

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function mediaText(Fact $fact, Media $media): string
    {
        $parts = [$fact->label(), $fact->value(), $fact->gedcom(), $media->fullName(), $media->getNote()];
        foreach ($media->mediaFiles() as $file) {
            $parts[] = $file->filename();
            $parts[] = $file->title();
            $parts[] = $file->type();
            $parts[] = $file->format();
        }
        $text = html_entity_decode(implode(' ', $parts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(strip_tags($text));
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
