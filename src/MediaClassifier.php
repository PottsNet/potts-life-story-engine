<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Media;

/**
 * Classifies media by what it is and, only when there is good evidence, by
 * the chapter it illustrates.  Generic timeline photographs deliberately
 * fall back to Photographs and keepsakes rather than being assigned to the
 * nearest or broadest life event.
 */
final class MediaClassifier
{
    /** @var array<string,list<string>> */
    private const KIND_TERMS = [
        'photograph'  => ['photo', 'photograph', 'portrait', 'snapshot', 'image', 'picture'],
        'newspaper'   => ['newspaper', 'press clipping', 'news clipping', 'article', 'gazette', 'advocate', 'times'],
        'letter'      => ['letter', 'correspondence', 'post card', 'postcard', 'telegram'],
        'certificate' => ['certificate', 'registration', 'licence', 'license', 'record extract'],
        'document'    => ['document', 'report', 'paper', 'record', 'notice', 'programme', 'program'],
        'keepsake'    => ['card', 'birthday card', 'invitation', 'keepsake', 'souvenir', 'memorabilia'],
    ];

    /**
     * Weighted terms.  Broad words such as family, mother, child and portrait
     * are intentionally excluded: they describe the subject but do not prove
     * that the image illustrates a particular event.
     *
     * @var array<string,array<string,int>>
     */
    private const CHAPTER_TERMS = [
        'origins' => [
            'birth certificate' => 100,
            'birth' => 95,
            'born' => 90,
            'christening' => 100,
            'baptism' => 100,
            'baptised' => 100,
            'baptized' => 100,
            'baby photo' => 92,
            'baby' => 88,
            'infant' => 88,
            'childhood' => 82,
        ],
        'family' => [
            'wedding certificate' => 100,
            'marriage certificate' => 100,
            'wedding' => 98,
            'marriage' => 96,
            'married' => 94,
            'bride' => 92,
            'groom' => 92,
            'anniversary' => 86,
        ],
        'learning' => [
            'graduation' => 96,
            'school certificate' => 95,
            'school report' => 94,
            'school class' => 92,
            'teachers college' => 92,
            'teacher training' => 92,
            'school' => 86,
            'college' => 86,
            'university' => 86,
            'class photo' => 88,
            'pupil' => 82,
            'student' => 82,
            'education' => 82,
        ],
        'career' => [
            'head teacher' => 94,
            'workplace' => 90,
            'staff photo' => 90,
            'employment' => 88,
            'occupation' => 88,
            'career' => 88,
            'teacher' => 84,
            'business' => 84,
            'factory' => 82,
            'office' => 80,
            'councillor' => 88,
            'mayor' => 88,
        ],
        'community' => [
            'military service' => 100,
            'service record' => 96,
            'army' => 94,
            'navy' => 94,
            'air force' => 94,
            'airforce' => 94,
            'soldier' => 92,
            'uniform' => 90,
            'anzac' => 94,
            'war service' => 94,
            'salvation army' => 90,
            'church service' => 88,
            'community service' => 88,
        ],
        'places' => [
            'house deed' => 94,
            'property title' => 94,
            'homestead' => 90,
            'family home' => 88,
            'house' => 84,
            'residence' => 84,
            'property' => 84,
            'farm' => 82,
            'land' => 80,
        ],
        'journey' => [
            'immigration' => 100,
            'emigration' => 100,
            'immigrant' => 96,
            'arrival' => 92,
            'departure' => 92,
            'passport' => 94,
            'voyage' => 92,
            'migration' => 96,
            'ship' => 86,
        ],
        'final' => [
            'death certificate' => 100,
            'death notice' => 100,
            'funeral' => 98,
            'obituary' => 98,
            'headstone' => 96,
            'grave' => 94,
            'cemetery' => 92,
            'burial' => 94,
            'memorial' => 90,
            'probate' => 94,
            'last will' => 94,
            'in memoriam' => 94,
        ],
    ];

    public function classify(Fact $fact): MediaClassification
    {
        $target = $fact->target();
        return $this->classifyWithMedia($fact, $target instanceof Media ? $target : null);
    }

    public function classifyWithMedia(Fact $fact, ?Media $target): MediaClassification
    {
        $textParts = [$fact->label(), $fact->value()];
        if ($target instanceof Media) {
            $textParts[] = $target->fullName();
            $textParts[] = $target->getNote();
            foreach ($target->mediaFiles() as $mediaFile) {
                $textParts[] = $mediaFile->filename();
                $textParts[] = $mediaFile->title();
                $textParts[] = $mediaFile->type();
                $textParts[] = $mediaFile->format();
            }
        }

        $text = $this->plain(implode(' ', $textParts));
        if ($text === '') {
            return new MediaClassification($target instanceof Media, $target instanceof Media ? 'document' : '');
        }

        $kind = '';
        $kindTerm = '';
        foreach (self::KIND_TERMS as $candidateKind => $terms) {
            foreach ($terms as $term) {
                if ($this->containsTerm($text, $term)) {
                    $kind = $candidateKind;
                    $kindTerm = $term;
                    break 2;
                }
            }
        }

        if ($kind === '' && $target instanceof Media) {
            $hasImage = $target->mediaFiles()->contains(static fn ($file): bool => $file->isImage());
            $kind = $hasImage ? 'photograph' : 'document';
            $kindTerm = $hasImage ? 'image' : 'document';
        }

        if ($kind === '') {
            return new MediaClassification(false);
        }

        [$targetChapter, $chapterScore, $chapterTerm] = $this->bestChapterMatch($text);

        // A generic photograph, portrait, letter or keepsake on the person's
        // timeline is a life memory, not evidence of a particular event.
        if ($chapterScore < 80) {
            $targetChapter = 'memories';
            $chapterTerm = '';
        }

        $confidence = $targetChapter === 'memories' ? 50 : $chapterScore;
        if (in_array($kind, ['newspaper', 'certificate'], true) && $targetChapter !== 'memories') {
            $confidence = min(100, $confidence + 4);
        }

        return new MediaClassification(
            true,
            $kind,
            $targetChapter,
            $confidence,
            $chapterTerm !== '' ? $chapterTerm : $kindTerm
        );
    }

    /** @return array{0:string,1:int,2:string} */
    private function bestChapterMatch(string $text): array
    {
        $bestChapter = 'memories';
        $bestScore = 0;
        $bestTerm = '';

        foreach (self::CHAPTER_TERMS as $chapter => $terms) {
            foreach ($terms as $term => $score) {
                if ($score > $bestScore && $this->containsTerm($text, $term)) {
                    $bestChapter = $chapter;
                    $bestScore = $score;
                    $bestTerm = $term;
                }
            }
        }

        return [$bestChapter, $bestScore, $bestTerm];
    }

    private function containsTerm(string $text, string $term): bool
    {
        return str_contains($text, mb_strtolower($term));
    }

    private function plain(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower(strip_tags($value));
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
