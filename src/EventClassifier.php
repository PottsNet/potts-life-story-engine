<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;

final class EventClassifier
{
    /** @var array<string,array{chapter:string,importance:string,icon:string,accent:string,score:int}> */
    private const MAP = [
        'BIRT' => ['chapter' => 'origins',   'importance' => 'major',    'icon' => '●', 'accent' => 'birth', 'score' => 100],
        'CHR'  => ['chapter' => 'origins',   'importance' => 'standard', 'icon' => '◇', 'accent' => 'birth', 'score' => 62],
        'BAPM' => ['chapter' => 'origins',   'importance' => 'standard', 'icon' => '◇', 'accent' => 'birth', 'score' => 60],
        'ADOP' => ['chapter' => 'origins',   'importance' => 'major',    'icon' => '●', 'accent' => 'family', 'score' => 92],
        'EDUC' => ['chapter' => 'learning',  'importance' => 'standard', 'icon' => '◆', 'accent' => 'education', 'score' => 70],
        'GRAD' => ['chapter' => 'learning',  'importance' => 'standard', 'icon' => '◆', 'accent' => 'education', 'score' => 78],
        'OCCU' => ['chapter' => 'career',    'importance' => 'standard', 'icon' => '■', 'accent' => 'career', 'score' => 76],
        'RETI' => ['chapter' => 'career',    'importance' => 'standard', 'icon' => '■', 'accent' => 'career', 'score' => 82],
        'IMMI' => ['chapter' => 'journey',   'importance' => 'major',    'icon' => '▲', 'accent' => 'journey', 'score' => 95],
        'EMIG' => ['chapter' => 'journey',   'importance' => 'major',    'icon' => '▲', 'accent' => 'journey', 'score' => 95],
        'NATU' => ['chapter' => 'journey',   'importance' => 'standard', 'icon' => '▲', 'accent' => 'journey', 'score' => 72],
        'MARR' => ['chapter' => 'family',    'importance' => 'major',    'icon' => '♥', 'accent' => 'family', 'score' => 95],
        'ENGA' => ['chapter' => 'family',    'importance' => 'standard', 'icon' => '♥', 'accent' => 'family', 'score' => 70],
        'DIV'  => ['chapter' => 'family',    'importance' => 'major',    'icon' => '◇', 'accent' => 'family', 'score' => 90],
        'ANUL' => ['chapter' => 'family',    'importance' => 'major',    'icon' => '◇', 'accent' => 'family', 'score' => 90],
        'RESI' => ['chapter' => 'places',    'importance' => 'minor',    'icon' => '⌂', 'accent' => 'place', 'score' => 52],
        'CENS' => ['chapter' => 'places',    'importance' => 'minor',    'icon' => '⌂', 'accent' => 'place', 'score' => 40],
        'PROP' => ['chapter' => 'places',    'importance' => 'standard', 'icon' => '⌂', 'accent' => 'property', 'score' => 66],
        'RELI' => ['chapter' => 'community', 'importance' => 'minor',    'icon' => '✦', 'accent' => 'community', 'score' => 48],
        'MILI' => ['chapter' => 'community', 'importance' => 'major',    'icon' => '★', 'accent' => 'service', 'score' => 92],
        '_MILT'=> ['chapter' => 'community', 'importance' => 'major',    'icon' => '★', 'accent' => 'service', 'score' => 92],
        'TITL' => ['chapter' => 'community', 'importance' => 'standard', 'icon' => '★', 'accent' => 'community', 'score' => 74],
        'WILL' => ['chapter' => 'final',     'importance' => 'major',    'icon' => '▤', 'accent' => 'final', 'score' => 86],
        'PROB' => ['chapter' => 'final',     'importance' => 'standard', 'icon' => '▤', 'accent' => 'final', 'score' => 68],
        'DEAT' => ['chapter' => 'final',     'importance' => 'major',    'icon' => '●', 'accent' => 'death', 'score' => 100],
        'BURI' => ['chapter' => 'final',     'importance' => 'standard', 'icon' => '◆', 'accent' => 'death', 'score' => 62],
        'CREM' => ['chapter' => 'final',     'importance' => 'standard', 'icon' => '◆', 'accent' => 'death', 'score' => 62],
    ];

    public function __construct(
        private readonly MediaClassifier $mediaClassifier,
        private readonly NoteExtractor $noteExtractor
    )
    {
    }

    public function mediaClassifier(): MediaClassifier
    {
        return $this->mediaClassifier;
    }

    /**
     * Return the story chapter represented by the GEDCOM fact itself, without
     * allowing a media title to reclassify the fact.  This is used when media
     * is explicitly attached to a real life event.
     */
    public function eventChapter(Fact $fact): ?string
    {
        $type = $this->type($fact);
        return self::MAP[$type]['chapter'] ?? null;
    }

    public function isRecognisedLifeEvent(Fact $fact): bool
    {
        return $this->eventChapter($fact) !== null;
    }

    public function classify(Fact $fact): StoryEvent
    {
        $type = $this->type($fact);
        $media = $this->mediaClassifier->classify($fact);

        if ($media->isMedia) {
            return new StoryEvent(
                $fact,
                $media->targetChapter,
                'minor',
                $type,
                $this->mediaIcon($media->kind),
                'memory',
                45 + intdiv($media->confidence, 5),
                true,
                $media->kind,
                $media->targetChapter,
                $media->confidence,
                null,
                null,
                [],
                [],
                null,
                '',
                null,
                'unknown',
                0,
                $this->noteExtractor->extract($fact)
            );
        }

        $config = self::MAP[$type] ?? $this->customConfig();

        return new StoryEvent(
            $fact,
            $config['chapter'],
            $config['importance'],
            $type,
            $config['icon'],
            $config['accent'],
            $config['score'],
            false,
            '',
            '',
            0,
            null,
            null,
            [],
            [],
            null,
            '',
            null,
            'unknown',
            0,
            $this->noteExtractor->extract($fact)
        );
    }

    public function chapterTitle(string $chapter): string
    {
        return match ($chapter) {
            'origins'   => I18N::translate('Origins and early life'),
            'learning'  => I18N::translate('Education and learning'),
            'journey'   => I18N::translate('Journeys and migration'),
            'family'    => I18N::translate('Family life'),
            'career'    => I18N::translate('Work and career'),
            'places'    => I18N::translate('Homes and places'),
            'community' => I18N::translate('Service and community'),
            'memories'  => I18N::translate('Photographs and keepsakes'),
            'final'     => I18N::translate('Final years and legacy'),
            default     => I18N::translate('Other life events'),
        };
    }

    /** @return array{chapter:string,importance:string,icon:string,accent:string,score:int} */
    private function customConfig(): array
    {
        return ['chapter' => 'other', 'importance' => 'minor', 'icon' => '•', 'accent' => 'other', 'score' => 20];
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

    private function type(Fact $fact): string
    {
        $parts = explode(':', $fact->tag());
        return (string) end($parts);
    }
}
