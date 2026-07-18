<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\Individual;

/**
 * Resolves the name used by the narrative while preserving the formal name
 * for the person's first introduction.
 *
 * Supported preference order:
 *  1. A given-name token ending in "*" in the primary NAME record.
 *  2. A NICK value attached to the primary NAME record or individual.
 *  3. The first given name.
 */
final class NameResolver
{
    public function formalName(Individual $individual): string
    {
        $name = $this->plain($individual->fullName());

        // The asterisk is a private GEDCOM convention and should never be
        // printed in the public biography.
        return trim((string) preg_replace('/(?<=\pL)\*/u', '', $name));
    }

    public function narrativeName(Individual $individual): string
    {
        $gedcom = str_replace("\r\n", "\n", $individual->gedcom());
        $primaryNameBlock = $this->primaryNameBlock($gedcom);

        $marked = $this->markedGivenName($primaryNameBlock);
        if ($marked !== '') {
            return $marked;
        }

        $nickname = $this->nickname($primaryNameBlock, $gedcom);
        if ($nickname !== '') {
            return $nickname;
        }

        $formal = $this->formalName($individual);
        $parts = preg_split('/\s+/u', trim($formal));

        return is_array($parts) && $parts !== [] ? (string) $parts[0] : $formal;
    }


    /**
     * Explain which GEDCOM convention selected the narrative name.
     */
    public function preferenceSource(Individual $individual): string
    {
        $gedcom = str_replace("\r\n", "\n", $individual->gedcom());
        $primaryNameBlock = $this->primaryNameBlock($gedcom);

        if ($this->markedGivenName($primaryNameBlock) !== '') {
            return 'asterisk';
        }

        if ($this->nickname($primaryNameBlock, $gedcom) !== '') {
            return 'nickname';
        }

        return 'first-given';
    }

    public function knownAsPhrase(Individual $individual): string
    {
        $formal = $this->formalName($individual);
        $working = $this->narrativeName($individual);
        $parts = preg_split('/\s+/u', $formal) ?: [];
        $first = $parts[0] ?? $formal;

        return $working !== '' && mb_strtolower($working) !== mb_strtolower($first)
            ? $working
            : '';
    }

    private function primaryNameBlock(string $gedcom): string
    {
        if (!preg_match('/^1 NAME .*(?:\n(?!1 ).*)*/m', $gedcom, $match)) {
            return '';
        }

        return $match[0];
    }

    private function markedGivenName(string $nameBlock): string
    {
        if ($nameBlock === '' || !preg_match('/^1 NAME\s+(.+?)(?:\s+\/[^\/]*\/)?\s*$/m', $nameBlock, $match)) {
            return '';
        }

        $givenText = trim($match[1]);
        $tokens = preg_split('/\s+/u', $givenText) ?: [];

        foreach ($tokens as $token) {
            if (str_ends_with($token, '*')) {
                return trim($token, "* \t\n\r\0\x0B\"");
            }
        }

        return '';
    }

    private function nickname(string $nameBlock, string $gedcom): string
    {
        foreach ([$nameBlock, $gedcom] as $source) {
            if ($source !== '' && preg_match('/^[12] NICK\s+(.+)$/m', $source, $match)) {
                return trim($this->plain($match[1]), " \t\n\r\0\x0B\"");
            }
        }

        return '';
    }

    private function plain(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }
}
