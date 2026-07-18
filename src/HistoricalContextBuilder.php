<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Illuminate\Support\Collection;

final class HistoricalContextBuilder
{
    private const MAX_ITEMS = 6;

    private HistoricalFactsProvider $historicalFacts;

    public function __construct(HistoricalFactsProvider $historicalFacts)
    {
        $this->historicalFacts = $historicalFacts;
    }

    /** @return list<HistoricalContextItem> */
    public function build(Individual $individual): array
    {
        if (!$this->historicalFacts->isAvailable()) {
            return [];
        }

        $birthYear = $this->eventYear($individual, 'BIRT');

        if ($birthYear === null) {
            return [];
        }

        $deathYear = $this->eventYear($individual, 'DEAT') ?? min((int) date('Y'), $birthYear + 120);

        // A person's dated places are the primary source for historical
        // context. This preserves country changes at immigration, emigration
        // and later residence events even when the visitor has selected a
        // custom Historical Facts collection for the ordinary facts tab.
        $segments = $this->countrySegments($individual, $birthYear, $deathYear);

        if ($segments === []) {
            // When visible genealogy data does not establish a country, fall
            // back to the visitor's explicit collection choice or the
            // Historical Facts site defaults.
            $segments = [[
                'code' => '',
                'name' => '',
                'start_year' => $birthYear,
                'end_year' => $deathYear,
                'collections' => $this->historicalFacts->visitorCollectionCodes() ?? [],
            ]];
        }

        $profile = $this->personalProfile($individual);
        $rows = [];
        $seen = [];

        foreach ($segments as $segment) {
            foreach ($this->historicalFacts->rows($segment['collections']) as $row) {
                $startYear = $this->yearFromDate($row['date']);
                $endYear = $row['end_date'] !== '' ? $this->yearFromDate($row['end_date']) : null;

                if ($startYear === null) {
                    continue;
                }

                $effectiveEnd = $endYear ?? $startYear;
                if ($effectiveEnd < $segment['start_year'] || $startYear > $segment['end_year']) {
                    continue;
                }

                $key = mb_strtolower($segment['code'] . '|' . $row['date'] . '|' . $row['end_date'] . '|' . $row['event_text']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $rows[] = [
                    'start_year'  => $startYear,
                    'end_year'    => $endYear,
                    'date'        => $row['end_date'] !== '' ? $row['date'] . '–' . $row['end_date'] : $row['date'],
                    'text'        => $row['event_text'],
                    'category'    => $row['category'],
                    'url'         => $row['link'],
                    'age'         => max(0, $startYear - $birthYear),
                    'score'       => $this->score($row['event_text'], $row['category'], $startYear, $birthYear, $deathYear, $profile),
                    'topic'       => $this->topicKey($row['event_text'], $row['category']),
                    'chapter'     => $this->relatedChapter($row['event_text'], $row['category']),
                    'country_code' => $segment['code'],
                    'country_name' => $segment['name'],
                ];
            }
        }

        if ($rows === []) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            $score = $b['score'] <=> $a['score'];
            return $score !== 0 ? $score : ($a['start_year'] <=> $b['start_year']);
        });

        $selected = [];
        $selectedTopics = [];
        $countryCounts = [];
        $countryTotal = max(1, count(array_filter($segments, static fn (array $segment): bool => $segment['code'] !== '')));
        $minimumPerCountry = $countryTotal > 1 ? 1 : 0;

        // First reserve at least one meaningful event from each country period,
        // where possible, so an immigrant's earlier life is not overwhelmed by
        // a much longer later residence.
        if ($minimumPerCountry > 0) {
            foreach ($segments as $segment) {
                if ($segment['code'] === '') {
                    continue;
                }
                foreach ($rows as $row) {
                    if ($row['country_code'] !== $segment['code']) {
                        continue;
                    }
                    $selected[] = $row;
                    $countryCounts[$segment['code']] = 1;
                    if ($row['topic'] !== '') {
                        $selectedTopics[$row['topic']] = true;
                    }
                    break;
                }
            }
        }

        foreach ($rows as $row) {
            if (count($selected) >= self::MAX_ITEMS) {
                break;
            }

            if (in_array($row, $selected, true)) {
                continue;
            }

            if ($row['topic'] !== '' && isset($selectedTopics[$row['topic']])) {
                continue;
            }

            $tooClose = false;
            foreach ($selected as $existing) {
                if ($existing['country_code'] === $row['country_code'] && abs($existing['start_year'] - $row['start_year']) <= 2 && $this->sameTheme($existing, $row)) {
                    $tooClose = true;
                    break;
                }
            }

            if ($tooClose) {
                continue;
            }

            $selected[] = $row;
            $countryCounts[$row['country_code']] = ($countryCounts[$row['country_code']] ?? 0) + 1;
            if ($row['topic'] !== '') {
                $selectedTopics[$row['topic']] = true;
            }
        }

        usort($selected, static fn (array $a, array $b): int => $a['start_year'] <=> $b['start_year']);

        return array_map(static fn (array $row): HistoricalContextItem => new HistoricalContextItem(
            $row['date'],
            $row['text'],
            $row['category'],
            $row['url'],
            $row['age'],
            $row['chapter'],
            $row['country_code'],
            $row['country_name']
        ), $selected);
    }

    /**
     * Build periods of residence by country from dated, visible facts.
     *
     * An explicit immigration/emigration/residence fact can pivot the country
     * immediately. Other strong life events (marriage, occupation, census,
     * death or burial) can also establish a move when their country differs
     * from the previous established country.
     *
     * @return list<array{code:string,name:string,start_year:int,end_year:int,collections:list<string>}>
     */
    private function countrySegments(Individual $individual, int $birthYear, int $deathYear): array
    {
        $locations = [];
        $acceptedTags = [
            'BIRT', 'CHR', 'BAPM', 'IMMI', 'EMIG', 'RESI', 'CENS', 'MARR',
            'OCCU', 'EDUC', 'GRAD', 'RETI', 'DEAT', 'BURI',
        ];

        foreach ($individual->facts([], false, null, true) as $fact) {
            $parts = explode(':', $fact->tag());
            $tag = (string) end($parts);
            if (!in_array($tag, $acceptedTags, true)) {
                continue;
            }

            $place = trim($fact->place()->gedcomName());
            $country = $this->countryFromPlace($place);
            $year = $this->factYear($fact);
            if ($country === null || $year === null || $year < $birthYear || $year > $deathYear) {
                continue;
            }

            $priority = match ($tag) {
                'IMMI', 'EMIG' => 100,
                'RESI', 'CENS' => 90,
                'BIRT', 'DEAT', 'BURI' => 85,
                'MARR' => 80,
                'OCCU', 'EDUC', 'GRAD', 'RETI' => 70,
                default => 60,
            };

            $locations[] = [
                'year' => $year,
                'code' => $country['code'],
                'name' => $country['name'],
                'priority' => $priority,
            ];
        }

        if ($locations === []) {
            return [];
        }

        usort($locations, static fn (array $a, array $b): int => ($a['year'] <=> $b['year']) ?: ($b['priority'] <=> $a['priority']));

        // Keep the strongest country evidence for each year.
        $byYear = [];
        foreach ($locations as $location) {
            if (!isset($byYear[$location['year']]) || $location['priority'] > $byYear[$location['year']]['priority']) {
                $byYear[$location['year']] = $location;
            }
        }
        ksort($byYear);

        $changes = [];
        $current = null;
        foreach ($byYear as $location) {
            if ($current === null || $location['code'] !== $current['code']) {
                $changes[] = $location;
                $current = $location;
            }
        }

        if ($changes === []) {
            return [];
        }

        // Begin at birth even if the first dated place record is a little later.
        $changes[0]['year'] = $birthYear;
        $segments = [];

        foreach ($changes as $index => $change) {
            $end = $index + 1 < count($changes) ? max($change['year'], $changes[$index + 1]['year'] - 1) : $deathYear;
            $segments[] = [
                'code' => $change['code'],
                'name' => $change['name'],
                'start_year' => max($birthYear, $change['year']),
                'end_year' => min($deathYear, $end),
                'collections' => $this->collectionsForCountry($change['code']),
            ];
        }

        return $segments;
    }

    private function factYear(Fact $fact): ?int
    {
        $minimum = (string) $fact->date()->minimumDate()->format('%Y');
        $year = $this->yearFromDate($minimum);
        if ($year !== null) {
            return $year;
        }

        $display = strip_tags($fact->date()->display());
        return $this->yearFromDate($display);
    }

    /** @return array{code:string,name:string}|null */
    private function countryFromPlace(string $place): ?array
    {
        if ($place === '') {
            return null;
        }

        $parts = preg_split('/\s*,\s*/', trim($place), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidate = mb_strtolower(trim((string) end($parts)));
        $candidate = preg_replace('/[.]/', '', $candidate) ?? $candidate;

        $aliases = [
            'australia' => ['AU', 'Australia'],
            'ireland' => ['IE', 'Ireland'],
            // Northern Ireland is part of the United Kingdom.  This is also
            // the most useful historical collection for places such as
            // Fermanagh when a GEDCOM uses the modern country name.
            'northern ireland' => ['GB', 'United Kingdom'],
            'n ireland' => ['GB', 'United Kingdom'],
            'republic of ireland' => ['IE', 'Ireland'],
            'eire' => ['IE', 'Ireland'],
            'england' => ['ENG', 'England'],
            'scotland' => ['SCT', 'Scotland'],
            'wales' => ['WLS', 'Wales'],
            'united kingdom' => ['GB', 'United Kingdom'],
            'great britain' => ['GB', 'United Kingdom'],
            'uk' => ['GB', 'United Kingdom'],
            'united states' => ['US', 'United States'],
            'united states of america' => ['US', 'United States'],
            'usa' => ['US', 'United States'],
            'u s a' => ['US', 'United States'],
            'canada' => ['CA', 'Canada'],
            'new zealand' => ['NZ', 'New Zealand'],
            'south africa' => ['ZA', 'South Africa'],
            'germany' => ['DE', 'Germany'],
            'france' => ['FR', 'France'],
            'italy' => ['IT', 'Italy'],
            'netherlands' => ['NL', 'Netherlands'],
            'india' => ['IN', 'India'],
            'china' => ['CN', 'China'],
            'austria' => ['AT', 'Austria'],
            'czech republic' => ['CZ', 'Czechia'],
            'czechia' => ['CZ', 'Czechia'],
            'poland' => ['PL', 'Poland'],
            'hungary' => ['HU', 'Hungary'],
            'greece' => ['GR', 'Greece'],
            'malta' => ['MT', 'Malta'],
            'slovakia' => ['SK', 'Slovakia'],
        ];

        if (!isset($aliases[$candidate])) {
            return null;
        }

        return ['code' => $aliases[$candidate][0], 'name' => $aliases[$candidate][1]];
    }

    /** @return list<string> */
    private function collectionsForCountry(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return [];
        }

        // Potts Historical Facts uses English collection identifiers as the
        // stable selection keys. Its provider chooses a matching translated
        // CSV for the current webtrees language where one is available.
        return ['en_' . $code, 'en_WLD'];
    }

    private function eventYear(Individual $individual, string $tag): ?int
    {
        /** @var Collection<int,Fact> $facts */
        $facts = $individual->facts([$tag], false, null, true);
        foreach ($facts as $fact) {
            $year = $this->yearFromDate((string) $fact->date()->minimumDate()->format('%Y'));
            if ($year !== null) {
                return $year;
            }

            $display = strip_tags($fact->date()->display());
            if (preg_match('/\b(\d{4})\b/', $display, $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /**
     * Build a small vocabulary from the person's visible places and occupations.
     *
     * @return array{places:list<string>,occupations:list<string>}
     */
    private function personalProfile(Individual $individual): array
    {
        $places = [];
        $occupations = [];

        foreach ($individual->facts([], false, null, true) as $fact) {
            $place = trim($fact->place()->gedcomName());
            if ($place !== '') {
                foreach (preg_split('/[,;]+/', mb_strtolower($place), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                    $part = trim($part);
                    if (mb_strlen($part) >= 4) {
                        $places[] = $part;
                    }
                }
            }

            $parts = explode(':', $fact->tag());
            $tag = (string) end($parts);
            if ($tag === 'OCCU') {
                $value = trim(mb_strtolower(strip_tags($fact->value())));
                if ($value !== '' && $value !== 'y') {
                    foreach (preg_split('/[,;\/]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                        $part = trim($part);
                        if (mb_strlen($part) >= 4) {
                            $occupations[] = $part;
                        }
                    }
                }
            }
        }

        return [
            'places'      => array_values(array_unique($places)),
            'occupations' => array_values(array_unique($occupations)),
        ];
    }

    private function yearFromDate(string $date): ?int
    {
        return preg_match('/\b(\d{4})\b/', $date, $match) === 1 ? (int) $match[1] : null;
    }

    /** @param array{places:list<string>,occupations:list<string>} $profile */
    private function score(string $text, string $category, int $year, int $birthYear, int $deathYear, array $profile): int
    {
        $haystack = mb_strtolower($text . ' ' . $category);
        $score = 0;

        // Direct personal relevance receives the strongest boost.
        foreach ($profile['places'] as $place) {
            if (str_contains($haystack, $place)) {
                $score += 14;
            }
        }

        foreach ($profile['occupations'] as $occupation) {
            if (str_contains($haystack, $occupation)) {
                $score += 10;
            }
        }

        // Themes commonly connected to a person's life story.
        foreach ([
            'education' => 7, 'school' => 7, 'teacher' => 7,
            'immigration' => 7, 'migration' => 7, 'gold rush' => 7,
            'war' => 5, 'military' => 5, 'federation' => 5,
            'election' => 4, 'vote' => 4, 'depression' => 4,
            'pandemic' => 4, 'railway' => 4, 'telegraph' => 4,
            'television' => 3, 'computer' => 3, 'colony' => 3, 'state' => 3,
        ] as $term => $weight) {
            if (str_contains($haystack, $term)) {
                $score += $weight;
            }
        }

        if (in_array($year, [$birthYear, $deathYear], true)) {
            $score += 2;
        }

        $lifeSpan = max(1, $deathYear - $birthYear);
        $relative = ($year - $birthYear) / $lifeSpan;
        if ($relative > 0.15 && $relative < 0.9) {
            $score += 1;
        }

        return $score;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function sameTheme(array $a, array $b): bool
    {
        if ($a['topic'] !== '' && $a['topic'] === $b['topic']) {
            return true;
        }

        return mb_strtolower((string) $a['category']) === mb_strtolower((string) $b['category']);
    }

    private function topicKey(string $text, string $category): string
    {
        $haystack = mb_strtolower($text . ' ' . $category);
        $topics = [
            'ww1' => ['first world war', 'world war i', 'great war', '1914–1918', '1914-1918'],
            'ww2' => ['second world war', 'world war ii', '1939–1945', '1939-1945'],
            'boer-war' => ['boer war', 'south african war'],
            'gold-rush' => ['gold rush'],
            'federation' => ['federation'],
            'women-vote' => ['women', 'vote', 'franchise'],
            'immigration' => ['immigration', 'migration scheme', 'migrant'],
            'education-reform' => ['education act', 'compulsory education', 'public education'],
            'depression' => ['great depression', 'depression'],
            'pandemic' => ['pandemic', 'influenza', 'spanish flu'],
        ];

        foreach ($topics as $key => $terms) {
            $matches = 0;
            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $matches++;
                }
            }
            if ($matches >= ($key === 'women-vote' ? 2 : 1)) {
                return $key;
            }
        }

        return '';
    }

    private function relatedChapter(string $text, string $category): string
    {
        $haystack = mb_strtolower($text . ' ' . $category);

        if (preg_match('/education|school|teacher|university|college/', $haystack) === 1) {
            return 'career';
        }
        if (preg_match('/migration|immigration|gold rush|railway|ship|travel/', $haystack) === 1) {
            return 'journey';
        }
        if (preg_match('/war|military|service|election|vote|community/', $haystack) === 1) {
            return 'community';
        }
        if (preg_match('/housing|home|property|settlement|town|city/', $haystack) === 1) {
            return 'places';
        }

        return 'other';
    }
}
