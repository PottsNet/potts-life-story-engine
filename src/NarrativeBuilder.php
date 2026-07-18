<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Illuminate\Support\Collection;

final class NarrativeBuilder
{
    public function __construct(private readonly NameResolver $nameResolver)
    {
    }
    /** @param Collection<int,StoryEvent> $events */
    public function storyProfile(Collection $events): StoryProfile
    {
        $occupationText = $events->where('type', 'OCCU')
            ->map(fn (StoryEvent $event): string => mb_strtolower($this->plain($event->fact->value())))
            ->filter(fn (string $value): bool => $this->useful($value))
            ->implode(' ');

        $hasMilitary = $events->whereIn('type', ['MILI', '_MILT'])->isNotEmpty();
        $hasMigration = $events->whereIn('type', ['IMMI', 'EMIG', 'NATU'])->isNotEmpty();

        if (preg_match('/teacher|schoolmaster|school teacher|head teacher|principal|educator|lecturer/u', $occupationText)) {
            return new StoryProfile('teacher', I18N::translate('Education and teaching'), ['origins', 'learning', 'family', 'career', 'places', 'community', 'memories', 'final', 'journey', 'other']);
        }
        if ($hasMilitary || preg_match('/soldier|military|army|navy|air force|airforce|service man|serviceman/u', $occupationText)) {
            return new StoryProfile('military', I18N::translate('Military and service life'), ['origins', 'learning', 'community', 'journey', 'family', 'career', 'places', 'memories', 'final', 'other']);
        }
        if (preg_match('/farmer|farming|grazier|agricultur|dairyman|dairy farmer|shearer/u', $occupationText)) {
            return new StoryProfile('farmer', I18N::translate('Farming and family life'), ['origins', 'journey', 'family', 'career', 'places', 'community', 'memories', 'final', 'learning', 'other']);
        }
        if (preg_match('/miner|mining|gold miner|prospector/u', $occupationText)) {
            return new StoryProfile('miner', I18N::translate('Mining and working life'), ['origins', 'journey', 'career', 'family', 'places', 'community', 'memories', 'final', 'learning', 'other']);
        }
        if (preg_match('/minister|pastor|clergy|priest|missionary|salvation army|chaplain|preacher/u', $occupationText)) {
            return new StoryProfile('faith', I18N::translate('Faith and community life'), ['origins', 'learning', 'community', 'family', 'journey', 'career', 'places', 'memories', 'final', 'other']);
        }
        if (preg_match('/councillor|councilor|mayor|justice of the peace|politician|member of parliament|public servant/u', $occupationText)) {
            return new StoryProfile('community', I18N::translate('Public and community life'), ['origins', 'learning', 'career', 'community', 'family', 'places', 'memories', 'final', 'journey', 'other']);
        }
        if ($hasMigration) {
            return new StoryProfile('migrant', I18N::translate('Migration and family life'), ['origins', 'journey', 'family', 'career', 'places', 'community', 'memories', 'final', 'learning', 'other']);
        }

        return new StoryProfile('general', I18N::translate('Family and working life'), ['origins', 'learning', 'journey', 'family', 'career', 'places', 'community', 'memories', 'final', 'other']);
    }
    public function lifeIntroduction(Individual $individual, Collection $events): string
    {
        $name = $this->nameResolver->formalName($individual);
        $firstName = $this->nameResolver->narrativeName($individual);
        $pronouns = $this->pronouns($individual);
        $birth = $events->firstWhere('type', 'BIRT');
        $death = $events->firstWhere('type', 'DEAT');
        $occupations = $events->where('type', 'OCCU')
            ->map(fn (StoryEvent $event): string => $this->plain($event->fact->value()))
            ->filter(fn (string $value): bool => $this->useful($value))
            ->unique()->values();

        $paragraph = [];

        if ($birth instanceof StoryEvent) {
            $date = $this->dateText($birth->fact);
            $place = trim($birth->fact->place()->gedcomName());
            $knownAs = $this->nameResolver->knownAsPhrase($individual);
            $introducedName = $knownAs !== ''
                ? I18N::translate('%s, known as %s,', $name, $knownAs)
                : $name;
            $paragraph[] = match (true) {
                $date !== '' && $place !== '' => I18N::translate('%s was born %s in %s.', $introducedName, $date, $place),
                $date !== '' => I18N::translate('%s was born %s.', $introducedName, $date),
                $place !== '' => I18N::translate('%s was born in %s.', $introducedName, $place),
                default => '',
            };
        }

        if ($occupations->isNotEmpty()) {
            $roles = $this->naturalList($occupations->take(3)->all());
            $careerPeriod = $this->periodFromEvents($events->where('type', 'OCCU'));
            if ($this->isTeachingCareer($occupations)) {
                $sentence = I18N::translate('Education became a defining thread in %s working life.', $pronouns['possessive']);
                if ($careerPeriod !== '') {
                    $sentence .= ' ' . I18N::translate('The surviving records trace this career from %s.', $careerPeriod);
                }
                $sentence .= ' ' . I18N::translate('Recorded roles included %s.', $roles);
                $paragraph[] = $sentence;
            } else {
                $paragraph[] = I18N::translate('%s working life included %s.', ucfirst($pronouns['possessive']), $roles);
            }
        }

        $familySentences = $this->familyIntroductionSentences($individual, $events);
        foreach ($familySentences as $familySentence) {
            $paragraph[] = $familySentence;
        }

        if ($death instanceof StoryEvent) {
            $date = $this->dateText($death->fact);
            $place = trim($death->fact->place()->gedcomName());
            $age = $this->ageAtDeath($birth, $death);
            $sentence = match (true) {
                $date !== '' && $place !== '' => I18N::translate('%s died %s in %s.', $firstName, $date, $place),
                $date !== '' => I18N::translate('%s died %s.', $firstName, $date),
                $place !== '' => I18N::translate('%s died in %s.', $firstName, $place),
                default => '',
            };
            if ($sentence !== '' && $age !== null) {
                $sentence = rtrim($sentence, '.') . ' ' . I18N::translate('at the age of %s.', I18N::number($age));
            }
            $paragraph[] = $sentence;
        }

        return trim(implode(' ', array_filter($paragraph)));
    }

    public function chapterTitle(string $chapter, Collection $events, ?StoryProfile $profile = null): string
    {
        return match ($chapter) {
            'origins' => I18N::translate('Early years'),
            'learning' => I18N::translate('Education and learning'),
            'journey' => I18N::translate('Journeys and migration'),
            'family' => $this->familyChapterTitle($events),
            'career' => $this->careerTitle($events, $profile),
            'places' => I18N::translate('Places called home'),
            'community' => I18N::translate('Community life'),
            'memories' => I18N::translate('Photographs and keepsakes'),
            'final' => I18N::translate('Final years and legacy'),
            default => I18N::translate('Other parts of the story'),
        };
    }

    public function chapterSubtitle(Individual $individual, string $chapter, Collection $events, ?StoryProfile $profile = null): string
    {
        $name = $this->nameResolver->formalName($individual);
        $firstName = $this->nameResolver->narrativeName($individual);
        $pronouns = $this->pronouns($individual);

        return match ($chapter) {
            'origins' => I18N::translate('The beginnings of %s recorded story.', $firstName . '’s'),
            'learning' => I18N::translate('The experiences that shaped %s knowledge and skills.', $pronouns['possessive']),
            'journey' => I18N::translate('The movements that carried %s story from place to place.', $firstName),
            'family' => I18N::translate('Relationships and family life became an important part of %s story.', $pronouns['possessive']),
            'career' => match ($profile?->key) {
                'teacher' => I18N::translate('A working life devoted to education.'),
                'military' => I18N::translate('Service, duty and the work that followed.'),
                'farmer' => I18N::translate('Work shaped by the land and rural life.'),
                'miner' => I18N::translate('Work, opportunity and the demands of mining life.'),
                'faith' => I18N::translate('Work and calling shaped by faith and service.'),
                'community' => I18N::translate('A working life connected with public service.'),
                default => I18N::translate('The work and responsibilities that shaped adult life.'),
            },
            'places' => I18N::translate('The homes and communities connected with %s life.', $pronouns['possessive']),
            'community' => I18N::translate('Service, beliefs and participation beyond the household.'),
            'memories' => I18N::translate('Images and keepsakes that preserve the personal side of the story.'),
            'final' => I18N::translate('The closing years and the legacy left behind.'),
            default => I18N::translate('Further records that add detail to the life story.'),
        };
    }

    public function chapterNarrative(Individual $individual, string $chapter, Collection $events, ?StoryProfile $profile = null): string
    {
        $name = $this->nameResolver->formalName($individual);
        $firstName = $this->nameResolver->narrativeName($individual);
        $pronouns = $this->pronouns($individual);
        $places = $events->map(static fn (StoryEvent $event): string => trim($event->fact->place()->gedcomName()))->filter()->unique()->values();
        $values = $events->map(fn (StoryEvent $event): string => $this->plain($event->fact->value()))
            ->filter(fn (string $value): bool => $this->useful($value))->unique()->values();

        return match ($chapter) {
            'origins' => $this->originsNarrative($firstName, $events, $places),
            'learning' => $this->learningNarrative($individual, $firstName, $events, $places),
            'journey' => $this->journeyNarrative($individual, $firstName, $pronouns, $events, $places),
            'family' => $this->familyNarrative($individual, $events),
            'career' => $this->careerNarrative($individual, $firstName, $pronouns, $events, $values, $profile),
            'places' => $this->placesNarrative($firstName, $pronouns, $events, $places),
            'community' => $this->communityNarrative($firstName, $events, $profile),
            'memories' => I18N::translate('Photographs, letters and keepsakes add a personal dimension to %s story, preserving moments that facts alone cannot convey.', $pronouns['possessive']),
            'final' => I18N::translate('The closing records trace %s later years and preserve the details of death, burial and remembrance.', $firstName),
            default => I18N::translate('Additional records add further texture and detail to %s life story.', $pronouns['possessive']),
        };
    }

    /**
     * Build a short bridge between two life-story chapters.
     *
     * @param Collection<int,StoryEvent> $previousEvents
     * @param Collection<int,StoryEvent> $currentEvents
     */
    public function chapterTransition(
        Individual $individual,
        ?string $previousChapter,
        string $currentChapter,
        Collection $previousEvents,
        Collection $currentEvents,
        ?StoryProfile $profile = null
    ): string {
        if ($previousChapter === null) {
            return '';
        }

        $firstName = $this->nameResolver->narrativeName($individual);
        $pronouns = $this->pronouns($individual);
        $currentYear = $this->firstUsableYear($currentEvents);
        // Years are identifiers, not quantities. Do not apply locale thousands separators.
        $year = $currentYear !== null ? (string) $currentYear : '';
        $firstEvent = $currentEvents->first(static fn (StoryEvent $event): bool => !$event->isMedia && $event->fact->date()->isOK());
        $chapterAge = $firstEvent instanceof StoryEvent ? $this->ageAtEvent($individual, $firstEvent) : null;
        $agePhrase = $chapterAge !== null && $chapterAge > 0
            ? I18N::translate(' at about %s years of age', I18N::number($chapterAge))
            : '';

        $transition = match ($currentChapter) {
            'learning' => $year !== ''
                ? I18N::translate('Around %s, when %s was%s, the story began to widen beyond the family home through education and learning.', $year, $firstName, $agePhrase)
                : I18N::translate('%s story began to widen beyond the family home through education and learning.', $firstName),
            'journey' => $year !== ''
                ? I18N::translate('In %s%s, a new stage began as travel and migration carried %s life into different places.', $year, $agePhrase, $pronouns['possessive'])
                : I18N::translate('A new stage began as travel and migration carried %s life into different places.', $pronouns['possessive']),
            'family' => $year !== ''
                ? I18N::translate('Around %s%s, relationships and family responsibilities opened another chapter in %s life.', $year, $agePhrase, $pronouns['possessive'])
                : I18N::translate('Relationships and family responsibilities opened another chapter in %s life.', $pronouns['possessive']),
            'career' => $this->careerTransition($firstName, $pronouns, $year, $profile),
            'places' => $year !== ''
                ? I18N::translate('During the years around %s, the surviving records begin to trace the homes and communities that shaped everyday life.', $year)
                : I18N::translate('The surviving records begin to trace the homes and communities that shaped everyday life.'),
            'community' => $year !== ''
                ? I18N::translate('By %s, %s life had extended beyond home and work into service, belief and community.', $year, $firstName)
                : I18N::translate('%s life extended beyond home and work into service, belief and community.', $firstName),
            'memories' => I18N::translate('Alongside the formal records, photographs and keepsakes preserve the more personal texture of %s life.', $pronouns['possessive']),
            'final' => $year !== ''
                ? I18N::translate('Later, from about %s, the story entered its closing years and the records of remembrance and legacy.', $year)
                : I18N::translate('Later in life, the story entered its closing years and the records of remembrance and legacy.'),
            default => '',
        };

        $previousYear = $this->lastUsableYear($previousEvents);
        $relativeLead = $this->relativeTimeLead($previousYear, $currentYear);
        if ($relativeLead !== '' && $transition !== '' && !in_array($currentChapter, ['memories', 'final'], true)) {
            return $relativeLead . ' ' . lcfirst($transition);
        }

        return $transition;
    }


    /** @param Collection<int,StoryEvent> $events */
    private function lastUsableYear(Collection $events): ?int
    {
        $years = $events->map(static function (StoryEvent $event): ?int {
            $date = $event->fact->date();
            if (!$date->isOK()) {
                return $event->mediaEffectiveYear;
            }
            $maximum = $date->maximumDate();
            return $maximum->year > 0 ? $maximum->year : $event->mediaEffectiveYear;
        })->filter(static fn (?int $year): bool => $year !== null && $year > 0)->sort()->values();

        return $years->last();
    }

    private function relativeTimeLead(?int $previousYear, ?int $currentYear): string
    {
        if ($previousYear === null || $currentYear === null || $currentYear <= $previousYear) {
            return '';
        }

        $elapsed = $currentYear - $previousYear;
        return match (true) {
            $elapsed === 1 => I18N::translate('The following year,'),
            $elapsed <= 3 => I18N::translate('Only %s years later,', I18N::number($elapsed)),
            $elapsed <= 10 => I18N::translate('%s years later,', I18N::number($elapsed)),
            default => '',
        };
    }

    private function careerTransition(string $firstName, array $pronouns, string $year, ?StoryProfile $profile): string
    {
        $opening = $year !== ''
            ? I18N::translate('As %s approached, ', $year)
            : I18N::translate('As adult life developed, ');

        return $opening . match ($profile?->key) {
            'teacher' => I18N::translate('education became the central thread of %s working life.', $firstName),
            'military' => I18N::translate('service and duty became important parts of %s adult life.', $firstName),
            'farmer' => I18N::translate('work and family life became closely connected with the land.'),
            'miner' => I18N::translate('work, opportunity and the demands of mining shaped this stage of life.'),
            'faith' => I18N::translate('faith, service and vocation became closely connected.'),
            'community' => I18N::translate('public responsibility became an increasingly important part of %s life.', $pronouns['possessive']),
            default => I18N::translate('work and responsibility became increasingly important in %s adult life.', $pronouns['possessive']),
        };
    }

    /**
     * A book-like stage label displayed above the chapter title.
     *
     * @param Collection<int,StoryEvent> $events
     */
    public function chapterStageLabel(string $chapter, Collection $events, ?StoryProfile $profile = null): string
    {
        return match ($chapter) {
            'origins' => I18N::translate('The early years'),
            'learning' => I18N::translate('Learning and preparation'),
            'journey' => I18N::translate('A journey begins'),
            'family' => I18N::translate('Marriage and family life'),
            'career' => match ($profile?->key) {
                'teacher' => I18N::translate('A life in education'),
                'military' => I18N::translate('Service and duty'),
                'farmer' => I18N::translate('A life on the land'),
                'miner' => I18N::translate('Work and opportunity'),
                'faith' => I18N::translate('Faith and vocation'),
                'community' => I18N::translate('Public life and service'),
                default => I18N::translate('Building a working life'),
            },
            'places' => I18N::translate('Places called home'),
            'community' => I18N::translate('Beyond home and work'),
            'memories' => I18N::translate('The personal record'),
            'final' => I18N::translate('The final chapter'),
            default => I18N::translate('Further parts of the story'),
        };
    }

    /** @param Collection<int,StoryEvent> $events */
    private function firstUsableYear(Collection $events): ?int
    {
        $years = $events->map(static function (StoryEvent $event): ?int {
            $date = $event->fact->date();
            if (!$date->isOK()) {
                return $event->mediaEffectiveYear;
            }
            $minimum = $date->minimumDate();
            return $minimum->year > 0 ? $minimum->year : $event->mediaEffectiveYear;
        })->filter(static fn (?int $year): bool => $year !== null && $year > 0)->sort()->values();

        return $years->first();
    }

    public function groupTitle(string $chapter, Collection $events, ?StoryProfile $profile = null): string
    {
        return match ($chapter) {
            'career' => $this->careerTitle($events, $profile),
            'places' => I18N::translate('Residence history'),
            'learning' => I18N::translate('Education history'),
            'community' => I18N::translate('Service and community record'),
            'memories' => I18N::translate('Keepsakes collection'),
            'illustrations' => $this->illustrationsGroupTitle($events),
            default => I18N::translate('Related events'),
        };
    }

    /** @param Collection<int,StoryEvent> $events */
    private function illustrationsGroupTitle(Collection $events): string
    {
        $kinds = $events->map(static fn (StoryEvent $event): string => $event->mediaKind)->filter()->unique();
        $hasPhotographs = $kinds->contains('photograph');
        $hasRecords = $kinds->contains(static fn (string $kind): bool => $kind !== 'photograph' && $kind !== 'keepsake');

        if ($hasPhotographs && !$hasRecords) {
            return I18N::translate('Photographs');
        }
        if (!$hasPhotographs) {
            return I18N::translate('Illustrations and records');
        }
        return I18N::translate('Photographs and records');
    }

    public function groupSummary(string $chapter, Collection $events): string
    {
        $places = $events->map(static fn (StoryEvent $event): string => trim($event->fact->place()->gedcomName()))->filter()->unique()->values();
        $values = $events->map(fn (StoryEvent $event): string => $this->plain($event->fact->value()))
            ->filter(fn (string $value): bool => $this->useful($value))->unique()->values();
        $period = $this->periodFromEvents($events);

        return match ($chapter) {
            'career' => $this->careerGroupSummary($events, $values, $places, $period),
            'places' => $this->placesGroupSummary($events, $places, $period),
            'learning' => $this->learningGroupSummary($events, $values, $places, $period),
            'community' => $this->communityGroupSummary($events, $values, $places, $period),
            'memories' => $this->memoriesGroupSummary($events, $values, $period),
            'illustrations' => $this->illustrationsGroupSummary($events),
            default => I18N::plural('One related event is preserved.', '%s related events are preserved.', $events->count(), I18N::number($events->count())),
        };
    }


    /** @param Collection<int,StoryEvent> $events */
    private function illustrationsGroupSummary(Collection $events): string
    {
        $kinds = $events->map(static fn (StoryEvent $event): string => $event->mediaKind)
            ->filter()
            ->countBy();

        $parts = [];
        foreach ($kinds as $kind => $count) {
            $label = match ($kind) {
                'photograph' => I18N::plural('%s photograph', '%s photographs', $count, I18N::number($count)),
                'newspaper' => I18N::plural('%s newspaper item', '%s newspaper items', $count, I18N::number($count)),
                'letter' => I18N::plural('%s letter or postcard', '%s letters or postcards', $count, I18N::number($count)),
                'certificate' => I18N::plural('%s certificate', '%s certificates', $count, I18N::number($count)),
                'document' => I18N::plural('%s document', '%s documents', $count, I18N::number($count)),
                default => I18N::plural('%s keepsake', '%s keepsakes', $count, I18N::number($count)),
            };
            $parts[] = $label;
        }

        if ($parts === []) {
            return I18N::plural('One illustration is associated with this chapter.', '%s illustrations are associated with this chapter.', $events->count(), I18N::number($events->count()));
        }

        return I18N::translate('This chapter includes %s.', $this->naturalList($parts));
    }

    /** @return list<array{year:string,label:string,chapter:string}> */
    public function milestones(Collection $events): array
    {
        $preferred = ['BIRT', 'IMMI', 'EMIG', 'MARR', 'OCCU', 'RETI', 'DEAT'];
        $labels = [
            'BIRT' => I18N::translate('Born'),
            'IMMI' => I18N::translate('Immigrated'),
            'EMIG' => I18N::translate('Emigrated'),
            'MARR' => I18N::translate('Married'),
            'OCCU' => I18N::translate('Career'),
            'RETI' => I18N::translate('Retired'),
            'DEAT' => I18N::translate('Died'),
        ];
        $seen = [];
        $result = [];
        foreach ($preferred as $type) {
            foreach ($events->where('type', $type) as $event) {
                if (!$event instanceof StoryEvent) {
                    continue;
                }
                $year = $this->yearText($event->fact);
                $label = $labels[$type] ?? $this->plain($event->fact->label());
                $key = $type . '|' . $year;
                if (!isset($seen[$key])) {
                    $result[] = ['year' => $year, 'label' => $label, 'chapter' => $event->chapter];
                    $seen[$key] = true;
                }
                if (count($result) >= 7) {
                    break 2;
                }
                break;
            }
        }
        return $result;
    }

    /** @return list<string> */
    public function highlights(Individual $individual, Collection $events, ?StoryProfile $profile = null): array
    {
        $highlights = [];
        $career = $events->where('type', 'OCCU');
        if ($career->count() >= 3) {
            $roles = $career->map(fn (StoryEvent $event): string => $this->plain($event->fact->value()))->filter(fn (string $v): bool => $this->useful($v))->unique()->values();
            $period = $this->periodFromEvents($career);
            $text = $this->isTeachingCareer($roles)
                ? I18N::translate('A substantial career in education is documented across %s appointments.', I18N::number($career->count()))
                : I18N::translate('%s career appointments are preserved in the record.', I18N::number($career->count()));
            if ($period !== '') {
                $text .= ' ' . I18N::translate('The recorded period extends across %s.', $period);
            }
            $highlights[] = $text;
        }

        $places = $events->filter(static fn (StoryEvent $event): bool => in_array($event->type, ['RESI', 'CENS'], true))
            ->map(static fn (StoryEvent $event): string => trim($event->fact->place()->gedcomName()))->filter()->unique()->values();
        if ($places->count() >= 2) {
            $highlights[] = I18N::translate('The record identifies %s places called home, including %s.', I18N::number($places->count()), $this->naturalList($places->take(3)->all()));
        }

        $children = $this->childCount($individual);
        if ($children > 0) {
            $highlights[] = I18N::plural('A family of %s child is recorded.', 'A family of %s children is recorded.', $children, I18N::number($children));
        }

        if ($events->whereIn('type', ['IMMI', 'EMIG'])->isNotEmpty()) {
            $highlights[] = I18N::translate('Migration formed an important turning point in this life story.');
        }
        if ($events->whereIn('type', ['MILI', '_MILT'])->isNotEmpty()) {
            $highlights[] = I18N::translate('Military or public service records survive for this person.');
        }

        if ($profile?->key === 'teacher') {
            $highlights[] = I18N::translate('Education was a defining thread in this life story.');
        } elseif ($profile?->key === 'farmer') {
            $highlights[] = I18N::translate('Work on the land formed an important part of this life story.');
        } elseif ($profile?->key === 'miner') {
            $highlights[] = I18N::translate('Mining or prospecting formed an important chapter of this life.');
        } elseif ($profile?->key === 'military') {
            $highlights[] = I18N::translate('Military or public service was a defining chapter of this life.');
        } elseif ($profile?->key === 'faith') {
            $highlights[] = I18N::translate('Faith and community service were closely connected in this life.');
        } elseif ($profile?->key === 'community') {
            $highlights[] = I18N::translate('Public service and community responsibility featured strongly in this life.');
        }

        return array_slice(array_values(array_unique($highlights)), 0, 4);
    }

    /**
     * Select a concise set of genuine turning points for the opening of the
     * biography. These are deliberately fewer and more interpretive than the
     * milestone strip: they highlight changes in direction rather than every
     * important fact.
     *
     * @return list<array{year:string,title:string,summary:string,chapter:string,accent:string}>
     */
    public function turningPoints(Individual $individual, Collection $events, ?StoryProfile $profile = null): array
    {
        $name = $this->nameResolver->narrativeName($individual);
        $points = [];

        $add = function (StoryEvent $event, string $title, string $summary, string $accent) use (&$points): void {
            $year = $this->yearText($event->fact);
            $key = $event->chapter . '|' . $year . '|' . $title;
            $points[$key] = [
                'year' => $year,
                'title' => $title,
                'summary' => $summary,
                'chapter' => $event->chapter,
                'accent' => $accent,
            ];
        };

        $migration = $events->first(static fn (StoryEvent $event): bool => in_array($event->type, ['IMMI', 'EMIG'], true));
        if ($migration instanceof StoryEvent) {
            $place = trim($migration->fact->place()->gedcomName());
            $summary = ($place !== ''
                ? I18N::translate('%s began a new chapter of life in %s.', $name, $place)
                : I18N::translate('Migration marked a major change in %s life.', $name . '’s')) . $this->ageClause($individual, $migration);
            $add($migration, I18N::translate('A new country'), $summary, 'journey');
        }

        $marriage = $events->firstWhere('type', 'MARR');
        if ($marriage instanceof StoryEvent) {
            $family = $marriage->fact->record();
            $spouse = $family instanceof Family ? $family->spouse($individual) : null;
            $spouseName = $spouse instanceof Individual ? $this->nameResolver->formalName($spouse) : '';
            $summary = ($spouseName !== ''
                ? I18N::translate('%s marriage to %s opened a new family chapter.', $name . '’s', $spouseName)
                : I18N::translate('Marriage opened a new family chapter in %s life.', $name . '’s')) . $this->ageClause($individual, $marriage);
            $add($marriage, I18N::translate('Marriage and family'), $summary, 'family');
        }

        $military = $events->first(static fn (StoryEvent $event): bool => in_array($event->type, ['MILI', '_MILT'], true));
        if ($military instanceof StoryEvent) {
            $add(
                $military,
                I18N::translate('Service'),
                I18N::translate('Service and public responsibility became part of %s story.', $name . '’s') . $this->ageClause($individual, $military),
                'service'
            );
        }

        /** @var Collection<int,StoryEvent> $career */
        $career = $events->where('type', 'OCCU')->values();
        if ($career->isNotEmpty()) {
            $featured = $career->sortByDesc(static fn (StoryEvent $event): int => $event->score)->first();
            if ($featured instanceof StoryEvent) {
                $role = $this->plain($featured->fact->value());
                $summary = ($this->useful($role)
                    ? I18N::translate('%s working life was shaped by %s.', $name . '’s', $role)
                    : I18N::translate('Work became a defining thread in %s life.', $name . '’s')) . $this->ageClause($individual, $featured);
                $title = $profile?->key === 'teacher'
                    ? I18N::translate('A life in education')
                    : I18N::translate('Working life');
                $add($featured, $title, $summary, 'career');
            }
        }

        $retirement = $events->firstWhere('type', 'RETI');
        if ($retirement instanceof StoryEvent) {
            $add(
                $retirement,
                I18N::translate('Retirement'),
                I18N::translate('Retirement marked the beginning of a later stage in %s life.', $name . '’s') . $this->ageClause($individual, $retirement),
                'career'
            );
        }

        $death = $events->firstWhere('type', 'DEAT');
        if ($death instanceof StoryEvent) {
            $place = trim($death->fact->place()->gedcomName());
            $summary = $place !== ''
                ? I18N::translate('%s life came to a close in %s.', $name . '’s', $place)
                : I18N::translate('%s final chapter is preserved in the surviving record.', $name . '’s');
            $add($death, I18N::translate('The final chapter'), $summary, 'final');
        }

        // Keep the feature selective. The opening should reveal the shape of
        // the life, not duplicate the complete chapter navigation.
        return array_slice(array_values($points), 0, 5);
    }

    public function coverQuote(Collection $events): string
    {
        foreach ($events as $event) {
            if (!$event instanceof StoryEvent || !in_array($event->type, ['NOTE', 'TEXT'], true)) {
                continue;
            }

            $text = $this->plain($event->fact->value());
            if (!$this->useful($text)) {
                continue;
            }

            $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence, " \t\n\r\0\x0B\"'“”‘’");
                $length = mb_strlen($sentence);
                if ($length >= 55 && $length <= 220 && !preg_match('/^(source|note|page|http)/iu', $sentence)) {
                    return $sentence;
                }
            }
        }

        return '';
    }

    private function originsNarrative(string $firstName, Collection $events, Collection $places): string
    {
        $birth = $events->firstWhere('type', 'BIRT');
        if ($birth instanceof StoryEvent) {
            $date = $this->dateText($birth->fact);
            $place = trim($birth->fact->place()->gedcomName());
            return match (true) {
                $date !== '' && $place !== '' => I18N::translate('%s story begins %s in %s, where the earliest known chapter of this life was recorded.', $firstName . '’s', $date, $place),
                $place !== '' => I18N::translate('%s story begins in %s, the place associated with the earliest surviving record.', $firstName . '’s', $place),
                default => I18N::translate('The earliest surviving records establish the beginnings of %s life and family story.', $firstName . '’s'),
            };
        }
        return $places->isNotEmpty()
            ? I18N::translate('The earliest surviving records connect %s with %s.', $firstName, $this->naturalList($places->take(2)->all()))
            : I18N::translate('The earliest surviving records establish the beginnings of %s life and family story.', $firstName . '’s');
    }

    private function learningNarrative(Individual $individual, string $firstName, Collection $events, Collection $places): string
    {
        $firstDated = $events->first(static fn (StoryEvent $event): bool => !$event->isMedia && $event->fact->date()->isOK());
        $startingAge = $firstDated instanceof StoryEvent ? $this->ageAtEvent($individual, $firstDated) : null;

        if ($events->count() > 1) {
            $sentence = I18N::translate('%s education can be followed through %s recorded stages of schooling or training.', $firstName . '’s', I18N::number($events->count()));
            if ($startingAge !== null && $startingAge > 0) {
                $sentence .= ' ' . I18N::translate('The earliest dated record places %s at about %s years of age.', $firstName, I18N::number($startingAge));
            }
            if ($places->isNotEmpty()) {
                $sentence .= ' ' . I18N::translate('These records are associated with %s.', $this->naturalList($places->take(3)->all()));
            }
            return $sentence;
        }
        return I18N::translate('The surviving record offers a glimpse of %s education or training.', $firstName . '’s');
    }

    private function familyNarrative(Individual $individual, Collection $events): string
    {
        $firstName = $this->nameResolver->narrativeName($individual);
        $relationships = $this->familyRelationships($individual, $events);

        if ($relationships === []) {
            $children = $this->childCount($individual);
            return $children > 0
                ? I18N::plural('%s was the parent of %s child.', '%s was the parent of %s children.', $children, $firstName, I18N::number($children))
                : I18N::translate('Family relationships formed an important part of %s life.', $firstName . '’s');
        }

        $sections = [];
        foreach ($relationships as $index => $relationship) {
            $marriage = $relationship['marriage'];
            $spouseName = $relationship['spouseName'];
            $children = $relationship['children'];

            if ($marriage instanceof StoryEvent) {
                $date = $this->dateText($marriage->fact);
                $place = trim($marriage->fact->place()->gedcomName());
                $marriageAge = $this->ageAtEvent($individual, $marriage);
                $lead = $index > 0 ? I18N::translate('Later,') . ' ' : '';

                $sentence = $lead . ($marriageAge !== null && $marriageAge > 0
                    ? ($spouseName !== ''
                        ? I18N::translate('at about %s years of age, %s married %s', I18N::number($marriageAge), $firstName, $spouseName)
                        : I18N::translate('at about %s years of age, %s married', I18N::number($marriageAge), $firstName))
                    : ($spouseName !== ''
                        ? I18N::translate('%s married %s', $firstName, $spouseName)
                        : I18N::translate('%s married', $firstName)));

                if ($index === 0) {
                    $sentence = ucfirst($sentence);
                }
                if ($date !== '') {
                    $sentence .= ' ' . $date;
                }
                if ($place !== '') {
                    $sentence .= ' ' . I18N::translate('in %s', $place);
                }
                $sentence .= '.';
            } elseif ($spouseName !== '') {
                $sentence = $index > 0
                    ? I18N::translate('Later, %s shared a family relationship with %s.', $firstName, $spouseName)
                    : I18N::translate('%s shared a family relationship with %s.', $firstName, $spouseName);
            } else {
                $sentence = '';
            }

            if ($children > 0) {
                $sentence .= ' ' . I18N::plural(
                    'They were the parents of %s child.',
                    'They were the parents of %s children.',
                    $children,
                    I18N::number($children)
                );
            }

            if (trim($sentence) !== '') {
                $sections[] = trim($sentence);
            }
        }

        $totalChildren = $this->childCount($individual);
        if (count($relationships) > 1 && $totalChildren > 0) {
            $sections[] = I18N::plural(
                'In total, %s was the parent of %s child.',
                'In total, %s was the parent of %s children.',
                $totalChildren,
                $firstName,
                I18N::number($totalChildren)
            );
        }

        return implode(' ', $sections);
    }

    /** @return list<string> */
    private function familyIntroductionSentences(Individual $individual, Collection $events): array
    {
        $firstName = $this->nameResolver->narrativeName($individual);
        $relationships = $this->familyRelationships($individual, $events);

        if ($relationships === []) {
            $children = $this->childCount($individual);
            return $children > 0
                ? [I18N::plural('%s was the parent of %s child.', '%s was the parent of %s children.', $children, $firstName, I18N::number($children))]
                : [];
        }

        $sentences = [];
        foreach ($relationships as $index => $relationship) {
            $marriage = $relationship['marriage'];
            $spouseName = $relationship['spouseName'];
            $children = $relationship['children'];
            $sentence = '';

            if ($marriage instanceof StoryEvent && $spouseName !== '') {
                $date = $this->dateText($marriage->fact);
                $place = trim($marriage->fact->place()->gedcomName());
                $detail = trim(implode(' ', array_filter([$date, $place !== '' ? I18N::translate('in %s', $place) : ''])));
                $sentence = $index > 0
                    ? ($detail !== ''
                        ? I18N::translate('Later, %s married %s %s.', $firstName, $spouseName, $detail)
                        : I18N::translate('Later, %s married %s.', $firstName, $spouseName))
                    : ($detail !== ''
                        ? I18N::translate('%s married %s %s.', $firstName, $spouseName, $detail)
                        : I18N::translate('%s married %s.', $firstName, $spouseName));
            } elseif ($spouseName !== '') {
                $sentence = $index > 0
                    ? I18N::translate('Later, %s shared a family relationship with %s.', $firstName, $spouseName)
                    : I18N::translate('%s shared a family relationship with %s.', $firstName, $spouseName);
            }

            if ($children > 0) {
                $sentence .= ' ' . I18N::plural(
                    'They were the parents of %s child.',
                    'They were the parents of %s children.',
                    $children,
                    I18N::number($children)
                );
            }

            if (trim($sentence) !== '') {
                $sentences[] = trim($sentence);
            }
        }

        $totalChildren = $this->childCount($individual);
        if (count($relationships) > 1 && $totalChildren > 0) {
            $sentences[] = I18N::plural(
                'In total, %s was the parent of %s child.',
                'In total, %s was the parent of %s children.',
                $totalChildren,
                $firstName,
                I18N::number($totalChildren)
            );
        }

        return $sentences;
    }

    /**
     * @return list<array{family:Family,spouseName:string,children:int,marriage:?StoryEvent}>
     */
    private function familyRelationships(Individual $individual, Collection $events): array
    {
        $marriagesByFamily = $events
            ->filter(static fn (StoryEvent $event): bool => $event->type === 'MARR' && $event->fact->record() instanceof Family)
            ->keyBy(static fn (StoryEvent $event): string => $event->fact->record()->xref());

        $relationships = [];
        foreach ($individual->spouseFamilies() as $family) {
            if (!$family instanceof Family) {
                continue;
            }
            $spouse = $family->spouse($individual);
            $relationships[] = [
                'family' => $family,
                'spouseName' => $spouse instanceof Individual ? $this->nameResolver->formalName($spouse) : '',
                'children' => $family->children()
                    ->filter(static fn ($child): bool => $child instanceof Individual)
                    ->unique(static fn (Individual $child): string => $child->xref())
                    ->count(),
                'marriage' => $marriagesByFamily->get($family->xref()),
            ];
        }

        usort($relationships, function (array $left, array $right): int {
            $leftMarriage = $left['marriage'];
            $rightMarriage = $right['marriage'];
            if ($leftMarriage instanceof StoryEvent && $rightMarriage instanceof StoryEvent) {
                return $leftMarriage->fact->date()->minimumJulianDay() <=> $rightMarriage->fact->date()->minimumJulianDay();
            }
            return $leftMarriage instanceof StoryEvent ? -1 : ($rightMarriage instanceof StoryEvent ? 1 : 0);
        });

        return $relationships;
    }

    private function familyChapterTitle(Collection $events): string
    {
        $familyCount = $events
            ->filter(static fn (StoryEvent $event): bool => $event->fact->record() instanceof Family)
            ->map(static fn (StoryEvent $event): string => $event->fact->record()->xref())
            ->unique()
            ->count();

        return $familyCount > 1 || $events->where('type', 'MARR')->count() > 1
            ? I18N::translate('Marriages and family')
            : I18N::translate('Marriage and family');
    }


    /** @param array{subject:string,object:string,possessive:string,reflexive:string} $pronouns */
    private function journeyNarrative(Individual $individual, string $firstName, array $pronouns, Collection $events, Collection $places): string
    {
        $movement = $events->first(static fn (StoryEvent $event): bool => in_array($event->type, ['IMMI', 'EMIG', 'NATU'], true) && $event->fact->date()->isOK());
        $age = $movement instanceof StoryEvent ? $this->ageAtEvent($individual, $movement) : null;
        $ageText = $age !== null && $age > 0
            ? I18N::translate(' at about %s years of age', I18N::number($age))
            : '';

        if ($places->isNotEmpty()) {
            $sentence = I18N::translate('%s journey connected %s, tracing important movements across the course of %s life.', ucfirst($pronouns['possessive']), $this->naturalList($places->take(4)->all()), $pronouns['possessive']);
            if ($age !== null && $age > 0) {
                $sentence .= ' ' . I18N::translate('The first dated migration record places %s at about %s years of age.', $firstName, I18N::number($age));
            }
            return $sentence;
        }

        return $ageText !== ''
            ? I18N::translate('The surviving records trace an important journey or migration in %s life, beginning%s.', $pronouns['possessive'], $ageText)
            : I18N::translate('The surviving records trace important journeys and migrations in %s life.', $pronouns['possessive']);
    }

    private function ageAtEvent(Individual $individual, StoryEvent $event): ?int
    {
        $birthDate = $individual->getBirthDate();
        $eventDate = $event->fact->date();
        if (!$birthDate->isOK() || !$eventDate->isOK()) {
            return null;
        }

        $years = (new Age($birthDate, $eventDate))->ageYears();
        return $years >= 0 && $years < 130 ? $years : null;
    }

    private function ageClause(Individual $individual, StoryEvent $event): string
    {
        $age = $this->ageAtEvent($individual, $event);
        return $age !== null && $age > 0
            ? I18N::translate(' At the time, %s was about %s years old.', $this->nameResolver->narrativeName($individual), I18N::number($age))
            : '';
    }

    private function careerTitle(Collection $events, ?StoryProfile $profile = null): string
    {
        $values = $events->map(fn (StoryEvent $event): string => mb_strtolower($this->plain($event->fact->value())))
            ->filter(fn (string $value): bool => $this->useful($value));
        if ($profile?->key === 'teacher' || $this->isTeachingCareer($values)) {
            return I18N::translate('Teaching career');
        }
        if ($profile?->key === 'farmer') {
            return I18N::translate('Farming life');
        }
        if ($profile?->key === 'miner') {
            return I18N::translate('Mining and working life');
        }
        if ($profile?->key === 'community') {
            return I18N::translate('Public life and work');
        }
        if ($values->contains(static fn (string $value): bool => str_contains($value, 'miner') || str_contains($value, 'mining'))) {
            return I18N::translate('Mining and working life');
        }
        if ($values->contains(static fn (string $value): bool => str_contains($value, 'farmer') || str_contains($value, 'farming'))) {
            return I18N::translate('Farming and working life');
        }
        return I18N::translate('Working life');
    }

    /** @param array{subject:string,object:string,possessive:string,reflexive:string} $pronouns */
    private function careerNarrative(Individual $individual, string $firstName, array $pronouns, Collection $events, Collection $values, ?StoryProfile $profile = null): string
    {
        if ($values->isEmpty()) {
            return I18N::translate('The surviving records offer a glimpse of %s working life.', $firstName . '’s');
        }
        $roles = $this->naturalList($values->take(4)->all());
        $period = $this->periodFromEvents($events);
        $firstDated = $events->first(static fn (StoryEvent $event): bool => !$event->isMedia && $event->fact->date()->isOK());
        $startingAge = $firstDated instanceof StoryEvent ? $this->ageAtEvent($individual, $firstDated) : null;
        $ageSentence = $startingAge !== null && $startingAge > 0
            ? ' ' . I18N::translate('The earliest dated appointment was recorded when %s was about %s.', $firstName, I18N::plural('%s year old', '%s years old', $startingAge, I18N::number($startingAge)))
            : '';
        if ($profile?->key === 'teacher' || $this->isTeachingCareer($values)) {
            $sentence = I18N::translate('Education became the defining thread of %s working life.', $firstName . '’s');
            if ($events->count() >= 3) {
                $sentence .= ' ' . I18N::translate('Across %s recorded appointments, %s progressed through roles including %s.', I18N::number($events->count()), $pronouns['subject'], $roles);
            } else {
                $sentence .= ' ' . I18N::translate('Recorded roles included %s.', $roles);
            }
            if ($period !== '') {
                $sentence .= ' ' . I18N::translate('The documented career spans %s.', $period);
            }
            return $sentence . $ageSentence;
        }
        if ($profile?->key === 'farmer') {
            $sentence = I18N::translate('The land formed an important part of %s working life.', $firstName . '’s');
            $sentence .= ' ' . I18N::translate('The surviving records include %s.', $roles);
            return ($period !== '' ? $sentence . ' ' . I18N::translate('This work is documented across %s.', $period) : $sentence) . $ageSentence;
        }
        if ($profile?->key === 'miner') {
            $sentence = I18N::translate('Mining and physical work shaped an important period of %s life.', $firstName . '’s');
            $sentence .= ' ' . I18N::translate('Recorded roles included %s.', $roles);
            return ($period !== '' ? $sentence . ' ' . I18N::translate('The documented period spans %s.', $period) : $sentence) . $ageSentence;
        }
        if ($profile?->key === 'community') {
            $sentence = I18N::translate('%s combined working life with public responsibility.', $firstName);
            $sentence .= ' ' . I18N::translate('Recorded roles included %s.', $roles);
            return ($period !== '' ? $sentence . ' ' . I18N::translate('The surviving record spans %s.', $period) : $sentence) . $ageSentence;
        }

        if ($events->count() >= 3) {
            $sentence = I18N::translate('%s working life is represented by %s recorded appointments, including %s.', $firstName . '’s', I18N::number($events->count()), $roles);
            return ($period !== '' ? $sentence . ' ' . I18N::translate('The documented period spans %s.', $period) : $sentence) . $ageSentence;
        }
        return I18N::translate('%s recorded working life included %s.', $firstName . '’s', $roles) . $ageSentence;
    }

    private function communityNarrative(string $firstName, Collection $events, ?StoryProfile $profile = null): string
    {
        if ($profile?->key === 'military') {
            return I18N::translate('Service became a defining chapter of %s life, with surviving records preserving military or public duties and the communities connected with them.', $firstName . '’s');
        }
        if ($profile?->key === 'faith') {
            return I18N::translate('Faith and service shaped %s place in the community, linking belief with public and family life.', $firstName . '’s');
        }
        if ($profile?->key === 'community') {
            return I18N::translate('Public responsibility formed an important part of %s life, with records of service, office and community participation.', $firstName . '’s');
        }
        return I18N::translate('These records preserve the ways %s took part in community life, including service, beliefs and public responsibilities.', $firstName);
    }

    private function placesNarrative(string $firstName, array $pronouns, Collection $events, Collection $places): string
    {
        if ($places->isEmpty()) {
            return I18N::translate('The surviving records identify places associated with %s life.', $pronouns['possessive']);
        }
        if ($places->count() === 1) {
            return I18N::translate('%s was recorded living in %s.', $firstName, (string) $places->first());
        }
        $sentence = I18N::translate('Across the surviving records, %s was associated with %s places called home.', $firstName, I18N::number($places->count()));
        $sentence .= ' ' . I18N::translate('These included %s.', $this->naturalList($places->take(5)->all()));
        if ($events->count() > $places->count()) {
            $sentence .= ' ' . I18N::translate('Some locations appear more than once, reflecting different stages of %s life.', $pronouns['possessive']);
        }
        return $sentence;
    }

    private function careerGroupSummary(Collection $events, Collection $values, Collection $places, string $period): string
    {
        $count = $events->count();
        $roleCount = $values->count();
        $summary = $count === 1
            ? I18N::translate('One working-life record is preserved.')
            : I18N::translate('%s working-life records are preserved.', I18N::number($count));

        if ($period !== '') {
            $summary .= ' ' . I18N::translate('The documented period spans %s.', $period);
        }

        if ($values->isNotEmpty()) {
            if ($roleCount === 1) {
                $summary .= ' ' . I18N::translate('The recorded role was %s.', (string) $values->first());
            } else {
                $summary .= ' ' . I18N::translate('Recorded roles included %s.', $this->naturalList($values->take(4)->all()));
                if ($roleCount > 4) {
                    $summary .= ' ' . I18N::translate('%s distinct role descriptions appear in the record.', I18N::number($roleCount));
                }
            }
        }

        if ($places->isNotEmpty()) {
            $summary .= ' ' . I18N::plural('Work was recorded in %s place.', 'Work was recorded in %s places.', $places->count(), I18N::number($places->count()));
        }

        return $summary;
    }

    private function placesGroupSummary(Collection $events, Collection $places, string $period): string
    {
        $eventCount = $events->count();
        $placeCount = $places->count();
        $summary = $eventCount === 1
            ? I18N::translate('One place of residence is recorded.')
            : I18N::translate('%s residence and census records are preserved.', I18N::number($eventCount));

        if ($period !== '') {
            $summary .= ' ' . I18N::translate('The recorded history spans %s.', $period);
        }

        if ($places->isNotEmpty()) {
            if ($placeCount === 1) {
                $summary .= ' ' . I18N::translate('The recorded home was %s.', (string) $places->first());
            } else {
                $summary .= ' ' . I18N::translate('Places called home included %s.', $this->naturalList($places->take(4)->all()));
                if ($placeCount > 4) {
                    $summary .= ' ' . I18N::translate('%s distinct locations are identified.', I18N::number($placeCount));
                }
            }
        }

        return $summary;
    }

    private function learningGroupSummary(Collection $events, Collection $values, Collection $places, string $period): string
    {
        $summary = I18N::plural('One stage of education is recorded.', '%s stages of education or training are recorded.', $events->count(), I18N::number($events->count()));
        if ($period !== '') {
            $summary .= ' ' . I18N::translate('The documented period spans %s.', $period);
        }
        if ($values->isNotEmpty()) {
            $summary .= ' ' . I18N::translate('The records mention %s.', $this->naturalList($values->take(3)->all()));
        }
        if ($places->isNotEmpty()) {
            $summary .= ' ' . I18N::plural('Study was associated with %s place.', 'Study was associated with %s places.', $places->count(), I18N::number($places->count()));
        }
        return $summary;
    }

    private function communityGroupSummary(Collection $events, Collection $values, Collection $places, string $period): string
    {
        $summary = I18N::plural('One service or community record is preserved.', '%s service or community records are preserved.', $events->count(), I18N::number($events->count()));
        if ($period !== '') {
            $summary .= ' ' . I18N::translate('The documented period spans %s.', $period);
        }
        if ($values->isNotEmpty()) {
            $summary .= ' ' . I18N::translate('Recorded service included %s.', $this->naturalList($values->take(3)->all()));
        }
        if ($places->isNotEmpty()) {
            $summary .= ' ' . I18N::plural('This service was linked to %s place.', 'This service was linked to %s places.', $places->count(), I18N::number($places->count()));
        }
        return $summary;
    }

    private function memoriesGroupSummary(Collection $events, Collection $values, string $period): string
    {
        $summary = I18N::plural('One photograph or keepsake adds detail to the story.', '%s photographs and keepsakes add detail to the story.', $events->count(), I18N::number($events->count()));
        if ($period !== '') {
            $summary .= ' ' . I18N::translate('The collection spans %s.', $period);
        }
        if ($values->isNotEmpty()) {
            $summary .= ' ' . I18N::translate('Items include %s.', $this->naturalList($values->take(3)->all()));
        }
        return $summary;
    }

    private function childCount(Individual $individual): int
    {
        return $individual->spouseFamilies()
            ->filter(static fn ($family): bool => $family instanceof Family)
            ->flatMap(static fn (Family $family): Collection => $family->children())
            ->unique(static fn (Individual $child): string => $child->xref())
            ->count();
    }

    private function dateText(Fact $fact): string
    {
        return $fact->date()->isOK() ? $this->plain($fact->date()->display()) : '';
    }

    private function yearText(Fact $fact): string
    {
        $date = $this->dateText($fact);
        return preg_match('/\b(1[0-9]{3}|20[0-9]{2})\b/', $date, $match) === 1 ? $match[1] : $date;
    }

    private function periodFromEvents(Collection $events): string
    {
        $years = $events
            ->map(fn (StoryEvent $event): string => $this->yearText($event->fact))
            ->filter(static fn (string $year): bool => preg_match('/^(1[0-9]{3}|20[0-9]{2})$/', $year) === 1)
            ->map(static fn (string $year): int => (int) $year)
            ->values();

        if ($years->isEmpty()) {
            return '';
        }

        $first = (int) $years->min();
        $last = (int) $years->max();
        return $first !== $last ? $first . '–' . $last : (string) $first;
    }

    private function ageAtDeath(?StoryEvent $birth, StoryEvent $death): ?int
    {
        if (!$birth instanceof StoryEvent) {
            return null;
        }
        $birthYear = $this->yearText($birth->fact);
        $deathYear = $this->yearText($death->fact);
        if (!ctype_digit($birthYear) || !ctype_digit($deathYear)) {
            return null;
        }
        $age = (int) $deathYear - (int) $birthYear;
        return $age >= 0 && $age < 130 ? $age : null;
    }

    /** @return array{subject:string,object:string,possessive:string,reflexive:string} */
    private function pronouns(Individual $individual): array
    {
        return match ($individual->sex()) {
            'M' => ['subject' => I18N::translate('he'), 'object' => I18N::translate('him'), 'possessive' => I18N::translate('his'), 'reflexive' => I18N::translate('himself')],
            'F' => ['subject' => I18N::translate('she'), 'object' => I18N::translate('her'), 'possessive' => I18N::translate('her'), 'reflexive' => I18N::translate('herself')],
            default => ['subject' => I18N::translate('they'), 'object' => I18N::translate('them'), 'possessive' => I18N::translate('their'), 'reflexive' => I18N::translate('themselves')],
        };
    }

    private function isTeachingCareer(Collection $values): bool
    {
        return $values->contains(static fn (string $value): bool => str_contains(mb_strtolower($value), 'teacher') || str_contains(mb_strtolower($value), 'school'));
    }

    /** @param list<string> $values */
    private function naturalList(array $values): string
    {
        $values = array_values(array_filter(array_map('trim', $values)));
        $count = count($values);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $values[0];
        }
        if ($count === 2) {
            return $values[0] . ' ' . I18N::translate('and') . ' ' . $values[1];
        }
        $last = array_pop($values);
        return implode(', ', $values) . ' ' . I18N::translate('and') . ' ' . $last;
    }

    private function useful(string $value): bool
    {
        return $value !== '' && $value !== 'Y';
    }

    private function plain(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }
}
