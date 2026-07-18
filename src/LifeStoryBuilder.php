<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Illuminate\Support\Collection;

final class LifeStoryBuilder
{
    /** @var array<string,array{types:list<string>,minimum:int,icon:string,accent:string}> */
    private const GROUPS = [
        'career' => [
            'types' => ['OCCU', 'RETI'],
            'minimum' => 3,
            'icon' => '■',
            'accent' => 'career',
        ],
        'places' => [
            'types' => ['RESI', 'CENS'],
            'minimum' => 3,
            'icon' => '⌂',
            'accent' => 'place',
        ],
        'learning' => [
            'types' => ['EDUC', 'GRAD'],
            'minimum' => 2,
            'icon' => '◆',
            'accent' => 'education',
        ],
        'community' => [
            'types' => ['MILI', '_MILT'],
            'minimum' => 2,
            'icon' => '★',
            'accent' => 'service',
        ],
    ];

    public function __construct(
        private readonly EventClassifier $classifier,
        private readonly NarrativeBuilder $narrativeBuilder,
        private readonly HistoricalContextBuilder $historicalContextBuilder,
        private readonly MediaPlacementEngine $mediaPlacementEngine,
        private readonly TimelineFactFilter $timelineFactFilter
    ) {
    }

    public function build(Individual $individual): LifeStory
    {
        $facts = $this->timelineFactFilter->filter(
            $individual->facts([], false, null, true)
        );

        foreach ($individual->spouseFamilies() as $family) {
            if (!$family instanceof Family) {
                continue;
            }

            $familyFacts = $family->facts(['ENGA', 'MARR', 'MARB', 'MARC', 'MARL', 'MARS', 'DIV', 'ANUL'], false, null, true);
            $facts = $facts->merge($familyFacts);
        }

        /** @var Collection<int,Fact> $facts */
        $facts = Fact::sortFacts($facts);

        /** @var Collection<string,Collection<int,StoryEvent>> $rawChapters */
        $rawChapters = new Collection();
        foreach ($facts as $fact) {
            $linkedMedia = $this->linkedMedia($fact);

            // Facts such as EVEN "Family Photo" often contain one or more
            // nested OBJE links.  Fact::target() only resolves a pointer that is
            // the fact value itself, so resolve nested links explicitly and
            // create one real media event for each linked Media object.
            if ($linkedMedia->isNotEmpty()) {
                // Placement hierarchy:
                // 1. Media explicitly attached to a recognised life event stays
                //    with that event (birth, baptism, marriage, occupation, etc.).
                // 2. A standalone media fact is assigned to a themed chapter only
                //    when its own metadata gives a strong, specific match.
                // 3. Generic timeline photos and keepsakes go to Memories.
                $explicitEventChapter = $this->classifier->eventChapter($fact);

                foreach ($linkedMedia as $media) {
                    $classification = $this->classifier->mediaClassifier()->classifyWithMedia($fact, $media);
                    $placement = $this->mediaPlacementEngine->place(
                        $individual,
                        $fact,
                        $media,
                        $classification,
                        $explicitEventChapter
                    );
                    $targetChapter = $placement->chapter;
                    $placementConfidence = $placement->confidence;

                    $event = new StoryEvent(
                        $fact,
                        $targetChapter,
                        'minor',
                        'OBJE',
                        $this->mediaIcon($classification->kind),
                        'memory',
                        45 + intdiv($placementConfidence, 5),
                        true,
                        $classification->kind,
                        $targetChapter,
                        $placementConfidence,
                        $media,
                        $placement->effectiveYear,
                        $placement->reasons,
                        $placement->scores,
                        $placement->ageAtMedia,
                        $placement->matchedEventLabel,
                        $placement->matchedEventYear,
                        $placement->dateSource,
                        $placement->dateConfidence
                    );
                    if (!$rawChapters->has($event->chapter)) {
                        $rawChapters->put($event->chapter, new Collection());
                    }
                    $rawChapters->get($event->chapter)?->push($event);
                }

                // A descriptive media fact is represented by the resolved media
                // events above. Do not also add a title-only duplicate card.
                $base = $this->classifier->classify($fact);
                if ($base->isMedia) {
                    continue;
                }
            }

            $event = $this->classifier->classify($fact);
            if (!$rawChapters->has($event->chapter)) {
                $rawChapters->put($event->chapter, new Collection());
            }
            $rawChapters->get($event->chapter)?->push($event);
        }

        // A media object may be linked at individual level and to one or more facts.
        // Display it once, in the chapter with the strongest classification match.
        /** @var array<string,StoryEvent> $bestMedia */
        $bestMedia = [];
        foreach ($rawChapters as $events) {
            foreach ($events as $event) {
                if (!$event->isMedia) {
                    continue;
                }
                $target = $event->media ?? $event->fact->target();
                if (!$target instanceof Media) {
                    continue;
                }
                $key = $target->xref();
                if (!isset($bestMedia[$key]) || $this->mediaContextRank($event) > $this->mediaContextRank($bestMedia[$key])) {
                    $bestMedia[$key] = $event;
                }
            }
        }
        foreach ($rawChapters as $chapterKey => $events) {
            $rawChapters->put($chapterKey, $events->filter(static function (StoryEvent $event) use ($bestMedia): bool {
                if (!$event->isMedia) {
                    return true;
                }
                $target = $event->media ?? $event->fact->target();
                return !$target instanceof Media || ($bestMedia[$target->xref()] ?? null) === $event;
            })->values());
        }
        $rawChapters = $rawChapters->filter(static fn (Collection $events): bool => $events->isNotEmpty());

        /** @var Collection<int,StoryEvent> $allEvents */
        $allEvents = $rawChapters->flatten(1)->values();
        $profile = $this->narrativeBuilder->storyProfile($allEvents);
        $order = $profile->chapterOrder;
        $rawChapters = $rawChapters->sortBy(static function (Collection $events, string $chapter) use ($order): int {
            $position = array_search($chapter, $order, true);
            return $position === false ? PHP_INT_MAX : $position;
        });

        /** @var Collection<string,StoryChapter> $chapters */
        $chapters = new Collection();
        $previousChapterKey = null;
        $previousChapterEvents = new Collection();
        foreach ($rawChapters as $chapterKey => $events) {
            $items = $this->groupChapter($chapterKey, $events, $profile);
            $chapters->put($chapterKey, new StoryChapter(
                $chapterKey,
                $this->narrativeBuilder->chapterTitle($chapterKey, $events, $profile),
                $this->narrativeBuilder->chapterStageLabel($chapterKey, $events, $profile),
                $this->narrativeBuilder->chapterSubtitle($individual, $chapterKey, $events, $profile),
                $this->narrativeBuilder->chapterNarrative($individual, $chapterKey, $events, $profile),
                $this->narrativeBuilder->chapterTransition(
                    $individual,
                    $previousChapterKey,
                    $chapterKey,
                    $previousChapterEvents,
                    $events,
                    $profile
                ),
                $items
            ));
            $previousChapterKey = $chapterKey;
            $previousChapterEvents = $events;
        }

        return new LifeStory(
            $chapters,
            $this->narrativeBuilder->lifeIntroduction($individual, $allEvents),
            $this->narrativeBuilder->milestones($allEvents),
            $this->narrativeBuilder->highlights($individual, $allEvents, $profile),
            $this->narrativeBuilder->turningPoints($individual, $allEvents, $profile),
            $this->historicalContextBuilder->build($individual),
            $this->narrativeBuilder->coverQuote($allEvents),
            $profile
        );
    }

    /**
     * @param Collection<int,StoryEvent> $events
     * @return Collection<int,StoryEvent|StoryGroup>
     */
    private function groupChapter(string $chapter, Collection $events, StoryProfile $profile): Collection
    {
        /** @var Collection<int,StoryEvent> $mediaEvents */
        $mediaEvents = $events->filter(static fn (StoryEvent $event): bool => $event->isMedia)->values();
        /** @var Collection<int,StoryEvent> $storyEvents */
        $storyEvents = $events->reject(static fn (StoryEvent $event): bool => $event->isMedia)->values();

        // Story-first presentation: chapter narrative, then illustrations,
        // then the structured evidence. This mirrors a published biography and
        // avoids making readers work through raw records before seeing the image
        // that explains the chapter.
        $items = new Collection();

        if ($mediaEvents->count() >= 2) {
            $items->push(new StoryGroup(
                'illustrations-' . $chapter,
                $this->narrativeBuilder->groupTitle('illustrations', $mediaEvents, $profile),
                $this->narrativeBuilder->groupSummary('illustrations', $mediaEvents),
                '▣',
                'memory',
                $mediaEvents,
                $this->featuredEvent('memories', $mediaEvents)
            ));
        } elseif ($mediaEvents->count() === 1) {
            $items->push($mediaEvents->first());
        }

        foreach ($this->groupStoryEvents($chapter, $storyEvents, $profile) as $storyItem) {
            $items->push($storyItem);
        }

        return $items;
    }

    /**
     * @param Collection<int,StoryEvent> $events
     * @return Collection<int,StoryEvent|StoryGroup>
     */
    private function groupStoryEvents(string $chapter, Collection $events, StoryProfile $profile): Collection
    {
        if ($chapter === 'memories' && $events->count() >= 3) {
            return new Collection([
                new StoryGroup(
                    'memories',
                    $this->narrativeBuilder->groupTitle('memories', $events, $profile),
                    $this->narrativeBuilder->groupSummary('memories', $events),
                    '▣',
                    'memory',
                    $events->values(),
                    $this->featuredEvent('memories', $events)
                ),
            ]);
        }

        $config = self::GROUPS[$chapter] ?? null;
        if ($config === null) {
            return $events;
        }

        $groupable = $events->filter(static fn (StoryEvent $event): bool => in_array($event->type, $config['types'], true));
        if ($groupable->count() < $config['minimum']) {
            return $events;
        }

        $group = new StoryGroup(
            $chapter,
            $this->narrativeBuilder->groupTitle($chapter, $groupable, $profile),
            $this->narrativeBuilder->groupSummary($chapter, $groupable),
            $config['icon'],
            $config['accent'],
            $groupable->values(),
            $this->featuredEvent($chapter, $groupable)
        );

        $firstIndex = $events->search(static fn (StoryEvent $event): bool => $groupable->contains($event));
        $items = new Collection();

        foreach ($events as $index => $event) {
            if ($index === $firstIndex) {
                $items->push($group);
            }
            if (!$groupable->contains($event)) {
                $items->push($event);
            }
        }

        return $items;
    }

    /** @param Collection<int,StoryEvent> $events */
    private function featuredEvent(string $chapter, Collection $events): ?StoryEvent
    {
        $featured = $events->sortByDesc(function (StoryEvent $event) use ($chapter): int {
            $fact = $event->fact;
            $value = trim(strip_tags(html_entity_decode($fact->value(), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $place = trim($fact->place()->gedcomName());
            $hasDate = $fact->date()->isOK();

            $score = $event->score;
            $score += $value !== '' && $value !== 'Y' ? 20 : 0;
            $score += $place !== '' ? 12 : 0;
            $score += $hasDate ? 8 : 0;

            if ($chapter === 'career') {
                $lower = mb_strtolower($value);
                if (preg_match('/head|chief|senior|manager|principal|superintendent|director|councillor|mayor/u', $lower)) {
                    $score += 18;
                }
                if ($event->type === 'RETI') {
                    $score -= 8;
                }
            } elseif ($chapter === 'learning') {
                $score += $event->type === 'GRAD' ? 18 : 0;
            } elseif ($chapter === 'places') {
                $score += $event->type === 'RESI' ? 8 : 0;
                $score -= $event->type === 'CENS' ? 4 : 0;
            } elseif ($chapter === 'community') {
                $score += in_array($event->type, ['MILI', '_MILT'], true) ? 15 : 0;
            } elseif ($chapter === 'memories') {
                $lower = mb_strtolower($value);
                if (preg_match('/family|wedding|school|military|portrait|newspaper|letter|funeral|house/u', $lower)) {
                    $score += 15;
                }
            }

            return $score;
        })->first();

        return $featured instanceof StoryEvent ? $featured : null;
    }

    /** @return Collection<int,Media> */
    private function linkedMedia(Fact $fact): Collection
    {
        preg_match_all('/^\d+ OBJE @([^@]+)@/m', $fact->gedcom(), $matches);

        return collect($matches[1] ?? [])
            ->unique()
            ->map(fn (string $xref): ?Media => Registry::mediaFactory()->make($xref, $fact->record()->tree()))
            ->filter(static fn ($media): bool => $media instanceof Media && $media->canShow())
            ->values();
    }

    private function mediaIcon(string $kind): string
    {
        return match ($kind) {
            'photograph' => '▣',
            'newspaper'  => '▤',
            'letter'     => '✉',
            'certificate'=> '◇',
            'document'   => '▥',
            default      => '◆',
        };
    }

    /**
     * Prefer the copy of a duplicated media object that carries the strongest
     * resolved timeline context. A media object can be linked both directly to
     * an individual and to a dated event. The dated event copy must win so its
     * year and age survive chapter relocation and de-duplication.
     */
    private function mediaContextRank(StoryEvent $event): int
    {
        $rank = $event->mediaDateConfidence * 10000;

        if ($event->mediaEffectiveYear !== null) {
            $rank += 2000;
        }
        if ($event->mediaAge !== null) {
            $rank += 1000;
        }
        if ($event->mediaDateSource === 'media-date') {
            $rank += 900;
        } elseif ($event->mediaDateSource === 'linked-event') {
            $rank += 800;
        }
        if ($event->mediaMatchedEventLabel !== '') {
            $rank += 400;
        }

        return $rank + $event->mediaConfidence;
    }

}
