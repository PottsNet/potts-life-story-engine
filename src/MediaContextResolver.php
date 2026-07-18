<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;

/**
 * Resolves one authoritative date for a media item.
 *
 * The resolver deliberately separates date resolution from chapter placement.
 * It never combines years from unrelated metadata and it never reads CHAN/DATE.
 */
final class MediaContextResolver
{
    /** @var array<string,MediaContext> */
    private array $cache = [];
    public function resolve(
        Individual $individual,
        Fact $fact,
        Media $media,
        ?string $explicitEventChapter
    ): MediaContext {
        $cacheKey = $individual->xref() . '|' . $fact->id() . '|' . $media->xref() . '|' . ($explicitEventChapter ?? '-');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $candidates = [];
        $linkedEventYear = null;
        if ($fact->date()->isOK()) {
            $candidate = $fact->date()->minimumDate()->year();
            if ($this->validYear($candidate)) {
                $linkedEventYear = $candidate;
            }
        }

        // 1. A genuine DATE directly on the media object is authoritative.
        foreach ($media->facts(['DATE']) as $dateFact) {
            if ($dateFact->date()->isOK()) {
                $year = $dateFact->date()->minimumDate()->year();
                if ($this->validYear($year)) {
                    $candidates[] = $this->candidate($year, 'media-date', 100, 'Used the DATE recorded directly on the media object', $linkedEventYear);
                }
            }
        }

        // 2. A dated fact that actually owns the media is more trustworthy than
        // a filename. This restores event-linked photos such as a 2003 family
        // photograph whose filename may contain a different archival year.
        if ($fact->date()->isOK() && ($explicitEventChapter !== null || $this->isStandaloneMediaFact($fact))) {
            $year = $fact->date()->minimumDate()->year();
            if ($this->validYear($year)) {
                $candidates[] = $this->candidate($year, 'linked-event', 95, 'Used the date of the event to which the media is attached', $linkedEventYear);
            }
        }

        // 3. Human-authored title text is useful, but remains inferred.
        foreach ($this->yearsFromText($media->fullName()) as $year) {
            $candidates[] = $this->candidate($year, 'media-title', 86, 'Inferred the year from the media title', $linkedEventYear);
        }
        foreach ($media->mediaFiles() as $file) {
            foreach ($this->yearsFromText($file->title()) as $year) {
                $candidates[] = $this->candidate($year, 'file-title', 82, 'Inferred the year from the file title', $linkedEventYear);
            }
        }

        // 4. Filenames are weaker. Camera counters, scan identifiers and folder
        // names can look like years, so they must never outrank a linked event.
        foreach ($media->mediaFiles() as $file) {
            foreach ($this->yearsFromFilename($file->filename()) as $year) {
                $candidates[] = $this->candidate($year, 'filename', 65, 'Inferred the year from the filename', $linkedEventYear);
            }
        }

        // 5. Notes are last because they can mention several unrelated years.
        foreach ($this->yearsFromText($media->getNote()) as $year) {
            $candidates[] = $this->candidate($year, 'media-note', 55, 'Inferred the year from the media note', $linkedEventYear);
        }

        if ($candidates === []) {
            return $this->cache[$cacheKey] = new MediaContext(null, 'unknown', 0, null, ['No reliable media date was available']);
        }

        // Prefer confidence, then agreement with other independent sources.
        $counts = [];
        foreach ($candidates as $candidate) {
            $counts[$candidate['year']] = ($counts[$candidate['year']] ?? 0) + 1;
        }
        foreach ($candidates as &$candidate) {
            $candidate['agreement'] = $counts[$candidate['year']] ?? 1;
        }
        unset($candidate);

        $birthYear = $this->birthYear($individual);
        $deathYear = $this->deathYear($individual);
        foreach ($candidates as &$candidate) {
            $candidate['plausible'] = $this->plausibleForLifetime($candidate['year'], $birthYear, $deathYear) ? 1 : 0;
        }
        unset($candidate);

        usort($candidates, static function (array $a, array $b): int {
            return [
                $b['plausible'],
                $b['confidence'],
                $b['agreement'],
                -$b['distance'],
                -$b['year'],
            ] <=> [
                $a['plausible'],
                $a['confidence'],
                $a['agreement'],
                -$a['distance'],
                -$a['year'],
            ];
        });

        $chosen = $candidates[0];
        $age = $birthYear !== null ? $chosen['year'] - $birthYear : null;
        if ($age !== null && ($age < 0 || $age > 125)) {
            $age = null;
        }

        $reasons = [$chosen['reason'] . ' (' . $chosen['year'] . ')'];
        $alternatives = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['year'] !== $chosen['year']
        ));
        if ($alternatives !== []) {
            $first = $alternatives[0];
            $reasons[] = 'Ignored lower-confidence ' . $first['source'] . ' year ' . $first['year'];
        }

        return $this->cache[$cacheKey] = new MediaContext(
            $chosen['year'],
            $chosen['source'],
            $chosen['confidence'],
            $age,
            $reasons
        );
    }

    /** @return array{year:int,source:string,confidence:int,reason:string,agreement:int,distance:int,plausible:int} */
    private function candidate(int $year, string $source, int $confidence, string $reason, ?int $linkedEventYear): array
    {
        return [
            'year' => $year,
            'source' => $source,
            'confidence' => $confidence,
            'reason' => $reason,
            'agreement' => 1,
            'distance' => $linkedEventYear === null ? 9999 : abs($year - $linkedEventYear),
            'plausible' => 1,
        ];
    }

    /** @return list<int> */
    private function yearsFromText(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/(?<!\d)(1[6-9]\d{2}|20\d{2}|21\d{2})(?!\d)/u', $text, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }

    /** @return list<int> */
    private function yearsFromFilename(string $filename): array
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        preg_match_all('/(?:^|[^0-9])(1[6-9]\d{2}|20\d{2}|21\d{2})(?:[^0-9]|$)/u', $basename, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }

    private function validYear(int $year): bool
    {
        return $year >= 1500 && $year <= ((int) date('Y') + 1);
    }

    private function birthYear(Individual $individual): ?int
    {
        $fact = $individual->facts(['BIRT'], false, null, true)->first();
        if ($fact instanceof Fact && $fact->date()->isOK()) {
            $year = $fact->date()->minimumDate()->year();
            return $this->validYear($year) ? $year : null;
        }

        return null;
    }

    private function deathYear(Individual $individual): ?int
    {
        $fact = $individual->facts(['DEAT'], false, null, true)->first();
        if ($fact instanceof Fact && $fact->date()->isOK()) {
            $year = $fact->date()->minimumDate()->year();
            return $this->validYear($year) ? $year : null;
        }

        return null;
    }

    private function plausibleForLifetime(int $year, ?int $birthYear, ?int $deathYear): bool
    {
        if ($birthYear !== null && $year < $birthYear - 1) {
            return false;
        }
        if ($deathYear !== null && $year > $deathYear + 1) {
            return false;
        }

        return true;
    }

    private function isStandaloneMediaFact(Fact $fact): bool
    {
        $parts = explode(':', $fact->tag());
        $type = (string) end($parts);
        if (!in_array($type, ['EVEN', 'OBJE', '_PHOTO'], true)) {
            return false;
        }

        $text = mb_strtolower(strip_tags($fact->label() . ' ' . $fact->value()));

        return preg_match('/\b(photo(?:graph)?|portrait|image|picture|letter|postcard|newspaper|certificate|document|keepsake)\b/u', $text) === 1;
    }
}
