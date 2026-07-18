<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Throwable;

/**
 * Optional bridge to Potts Historical Facts.
 *
 * The preferred integration uses the public provider method introduced in
 * Potts Historical Facts 1.1.1. A conservative file-based fallback keeps
 * Biography compatible with the earlier 1.1.0 release without making the
 * companion module a hard dependency.
 */
final class HistoricalFactsProvider
{
    private const MODULE_NAME = '_potts_historical_facts_';
    private const COLLECTION_COOKIE = 'potts_history_collections';
    private const LEGACY_COOKIE = 'potts_history_region';

    private bool $resolved = false;
    private ?ModuleInterface $module = null;

    public function isAvailable(): bool
    {
        return $this->module() instanceof ModuleInterface;
    }

    /**
     * Return an explicit visitor collection override, or null when Biography
     * should continue using its country-aware automatic selection.
     *
     * @return ?list<string>
     */
    public function visitorCollectionCodes(): ?array
    {
        $module = $this->module();

        if (!$module instanceof ModuleInterface) {
            return null;
        }

        try {
            if (method_exists($module, 'historicalContextSelectionForBiography')) {
                $selection = $module->historicalContextSelectionForBiography();

                if (is_array($selection) && ($selection['mode'] ?? '') === 'custom' && is_array($selection['codes'] ?? null)) {
                    $codes = array_values(array_unique(array_filter(array_map(
                        fn ($code): string => $this->normaliseCode((string) $code),
                        $selection['codes']
                    ))));

                    return $codes !== [] ? $codes : null;
                }

                return null;
            }
        } catch (Throwable $exception) {
            // Use the legacy cookie fallback below.
        }

        $raw = trim((string) ($_COOKIE[self::COLLECTION_COOKIE] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($_COOKIE[self::LEGACY_COOKIE] ?? ''));
        }

        if ($raw === '') {
            return null;
        }

        $codes = preg_split('/[,;|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codes = array_values(array_unique(array_filter(array_map([$this, 'normaliseCode'], $codes))));

        return $codes !== [] ? $codes : null;
    }

    /**
     * @param list<string> $collectionCodes Empty means the visitor/site selection.
     * @return list<array{date:string,end_date:string,event_text:string,link:string,category:string,collection_code:string}>
     */
    public function rows(array $collectionCodes): array
    {
        $module = $this->module();

        if (!$module instanceof ModuleInterface) {
            return [];
        }

        try {
            if (method_exists($module, 'historicalContextRowsForBiography')) {
                $rows = $module->historicalContextRowsForBiography(I18N::languageTag(), $collectionCodes);

                return $this->normaliseRows(is_array($rows) ? $rows : []);
            }
        } catch (Throwable $exception) {
            // Fall through to the compatibility reader. Biography must remain
            // usable even if an older companion module has malformed data.
        }

        return $this->fallbackRows($module, $collectionCodes);
    }

    private function module(): ?ModuleInterface
    {
        if ($this->resolved) {
            return $this->module;
        }

        $this->resolved = true;

        try {
            $service = Registry::container()->get(ModuleService::class);
            $module = $service->findByName(self::MODULE_NAME);

            if ($module instanceof ModuleInterface && $module->isEnabled()) {
                $this->module = $module;
            }
        } catch (Throwable $exception) {
            $this->module = null;
        }

        return $this->module;
    }

    /**
     * Compatibility reader for Potts Historical Facts 1.1.0.
     *
     * @param list<string> $collectionCodes
     * @return list<array{date:string,end_date:string,event_text:string,link:string,category:string,collection_code:string}>
     */
    private function fallbackRows(ModuleInterface $module, array $collectionCodes): array
    {
        $folders = $this->dataFolders($module);

        if ($folders === []) {
            return [];
        }

        $codes = $collectionCodes !== [] ? $collectionCodes : $this->selectedCodes($folders);
        $rows = [];
        $seen = [];

        foreach ($codes as $code) {
            $code = $this->normaliseCode($code);
            if ($code === '') {
                continue;
            }

            $file = $this->fileForCode($folders, $code, I18N::languageTag());
            if ($file === null) {
                continue;
            }

            foreach ($this->loadCsv($file, $this->canonicalSelectionCode($code)) as $row) {
                $key = mb_strtolower($row['date'] . '|' . $row['end_date'] . '|' . $row['event_text']);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function dataFolders(ModuleInterface $module): array
    {
        $folders = [];

        if (defined('WT_DATA_DIR') && is_string(WT_DATA_DIR) && WT_DATA_DIR !== '') {
            $userFolder = rtrim(WT_DATA_DIR, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . $module->name()
                . DIRECTORY_SEPARATOR . 'data'
                . DIRECTORY_SEPARATOR;

            if (is_dir($userFolder)) {
                $folders[] = $userFolder;
            }
        }

        try {
            if (method_exists($module, 'resourcesFolder')) {
                $resources = $module->resourcesFolder();
                if (is_string($resources) && $resources !== '') {
                    $bundled = rtrim($resources, DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
                    if (is_dir($bundled)) {
                        $folders[] = $bundled;
                    }
                }
            }
        } catch (Throwable $exception) {
            // Ignore an unusable companion module path.
        }

        return array_values(array_unique($folders));
    }

    /** @param list<string> $folders @return list<string> */
    private function selectedCodes(array $folders): array
    {
        $raw = trim((string) ($_COOKIE[self::COLLECTION_COOKIE] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($_COOKIE[self::LEGACY_COOKIE] ?? ''));
        }

        $codes = preg_split('/[,;|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($codes !== []) {
            return array_values(array_unique(array_filter(array_map([$this, 'normaliseCode'], $codes))));
        }

        foreach ($folders as $folder) {
            $defaultFile = $folder . 'default_region.txt';
            if (is_file($defaultFile)) {
                $default = $this->normaliseCode(trim((string) file_get_contents($defaultFile)));
                if ($default !== '') {
                    return [$default];
                }
            }
        }

        return ['en_AU'];
    }

    /** @param list<string> $folders */
    private function fileForCode(array $folders, string $code, string $languageTag): ?string
    {
        $preferred = $this->languagePreferredCode($code, $languageTag);
        $candidates = array_values(array_unique([$preferred, $code, $this->canonicalSelectionCode($code)]));

        foreach ($folders as $folder) {
            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }

                $path = $folder . $candidate . '.csv';
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    private function normaliseCode(string $code): string
    {
        $code = preg_replace('/\.csv$/i', '', trim($code)) ?? '';
        $code = str_replace('-', '_', $code);

        if ($code === '' || strtolower($code) === 'auto') {
            return '';
        }

        if (strtolower($code) === 'nl') {
            return 'nl_NL';
        }

        $parts = explode('_', $code);
        if (count($parts) >= 2) {
            $normalised = strtolower($parts[0]) . '_' . strtoupper($parts[1]);
        } else {
            $normalised = strtolower($parts[0]);
        }

        return preg_match('/^[a-z]{2}(?:_[A-Z]{2,3})?$/', $normalised) === 1 ? $normalised : '';
    }

    private function canonicalSelectionCode(string $code): string
    {
        $code = $this->normaliseCode($code);

        if (preg_match('/^[a-z]{2}_([A-Z]{2,3})$/', $code, $match) === 1) {
            return 'en_' . $match[1];
        }

        return $code;
    }

    private function languagePreferredCode(string $code, string $languageTag): string
    {
        $code = $this->normaliseCode($code);

        if (preg_match('/^[a-z]{2}_([A-Z]{2,3})$/', $code, $match) !== 1) {
            return $code;
        }

        $language = strtolower(strtok(str_replace('_', '-', $languageTag), '-') ?: 'en');

        return $language . '_' . $match[1];
    }

    /**
     * @return list<array{date:string,end_date:string,event_text:string,link:string,category:string,collection_code:string}>
     */
    private function loadCsv(string $file, string $collectionCode): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $rows = [];

        try {
            while (($columns = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                $columns = array_map(static fn ($value): string => trim((string) $value), $columns);
                $first = $columns[0] ?? '';

                if ($first === '' || str_starts_with($first, '#') || strtolower($first) === 'date') {
                    continue;
                }

                $text = trim(strip_tags($columns[2] ?? ''));
                $date = trim($columns[0] ?? '');
                if ($date === '' || $text === '') {
                    continue;
                }

                $url = trim($columns[3] ?? '');
                if (!$this->safeUrl($url)) {
                    $url = '';
                }

                $rows[] = [
                    'date' => $date,
                    'end_date' => trim($columns[1] ?? ''),
                    'event_text' => $text,
                    'link' => $url,
                    'category' => trim(strip_tags($columns[4] ?? '')),
                    'collection_code' => $collectionCode,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function safeUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param array<int,mixed> $rows
     * @return list<array{date:string,end_date:string,event_text:string,link:string,category:string,collection_code:string}>
     */
    private function normaliseRows(array $rows): array
    {
        $normalised = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = trim((string) ($row['date'] ?? ''));
            $text = trim(strip_tags((string) ($row['event_text'] ?? '')));
            if ($date === '' || $text === '') {
                continue;
            }

            $url = trim((string) ($row['link'] ?? ''));
            if (!$this->safeUrl($url)) {
                $url = '';
            }

            $item = [
                'date' => $date,
                'end_date' => trim((string) ($row['end_date'] ?? '')),
                'event_text' => $text,
                'link' => $url,
                'category' => trim(strip_tags((string) ($row['category'] ?? ''))),
                'collection_code' => $this->normaliseCode((string) ($row['collection_code'] ?? '')),
            ];

            $key = mb_strtolower($item['date'] . '|' . $item['end_date'] . '|' . $item['event_text']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalised[] = $item;
        }

        return $normalised;
    }
}
