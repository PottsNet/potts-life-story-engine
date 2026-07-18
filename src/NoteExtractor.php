<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;

use function array_values;
use function html_entity_decode;
use function mb_strlen;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function strip_tags;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PREG_SET_ORDER;

final class NoteExtractor
{
    private const LONG_NOTE_CHARACTERS = 360;

    /** @return list<StoryNote> */
    public function extract(Fact $fact): array
    {
        if (preg_match_all('/\n(2 NOTE\b.*(?:\n[^2].*)*)/', $fact->gedcom(), $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        /** @var array<string,StoryNote> $notes */
        $notes = [];

        foreach ($matches as $match) {
            $fragment = trim((string) ($match[1] ?? ''));
            if ($fragment === '') {
                continue;
            }

            $linked = false;
            $text = '';

            if (preg_match('/^2 NOTE @([^@]+)@/', $fragment, $pointer) === 1) {
                $linked = true;
                $note = Registry::noteFactory()->make((string) $pointer[1], $fact->record()->tree());
                if (!$note instanceof Note || !$note->canShow()) {
                    continue;
                }
                $text = $note->getNote();
            } else {
                $text = $this->inlineText($fragment);
            }

            $plain = $this->plain($text);
            if ($plain === '') {
                continue;
            }

            $key = hash('sha256', $fragment);
            $notes[$key] = new StoryNote(
                $fragment,
                $plain,
                mb_strlen($plain) > self::LONG_NOTE_CHARACTERS,
                $linked
            );
        }

        return array_values($notes);
    }

    private function inlineText(string $fragment): string
    {
        if (preg_match('/^2 NOTE ?(.*(?:\n3 (?:CONT|CONC) ?.*)*)/s', $fragment, $match) !== 1) {
            return '';
        }

        $text = (string) $match[1];
        $text = preg_replace('/\n3 CONT ?/', "\n", $text) ?? $text;
        $text = preg_replace('/\n3 CONC ?/', '', $text) ?? $text;

        return $text;
    }

    private function plain(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
