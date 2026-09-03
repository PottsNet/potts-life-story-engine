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
        $given = $this->firstGivenName($primaryNameBlock, $formal);

        return $given !== '' ? $given : $formal;
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
        $gedcom = str_replace("\r\n", "\n", $individual->gedcom());
        $primaryNameBlock = $this->primaryNameBlock($gedcom);
        $formal = $this->formalName($individual);
        $working = $this->narrativeName($individual);
        $first = $this->firstGivenName($primaryNameBlock, $formal);

        return $working !== '' && $first !== '' && mb_strtolower($working) !== mb_strtolower($first)
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

    private function firstGivenName(string $nameBlock, string $formal): string
    {
        if ($nameBlock !== '' && preg_match('/^2 GIVN\s+(.+)$/m', $nameBlock, $match)) {
            $given = $this->firstNameToken($this->plain($match[1]));
            if ($given !== '') {
                return $given;
            }
        }

        if ($nameBlock !== '' && preg_match('/^1 NAME\s+(.+?)(?:\s+\/[^\/]*\/)?\s*$/m', $nameBlock, $match)) {
            $givenText = $this->plain($match[1]);

            // NAME may include an honorific/prefix before the given names.
            // Prefer the explicit GIVN value above, but when it is absent use
            // NPFX to remove the prefix before taking the first given token.
            if (preg_match('/^2 NPFX\s+(.+)$/m', $nameBlock, $prefixMatch)) {
                $prefix = $this->plain($prefixMatch[1]);
                if ($prefix !== '') {
                    $givenText = preg_replace('/^' . preg_quote($prefix, '/') . '\s+/iu', '', $givenText) ?? $givenText;
                }
            }

            $given = $this->firstNameToken($givenText);
            if ($given !== '') {
                return $given;
            }
        }

        return $this->firstNameToken($formal);
    }

    private function firstNameToken(string $value): string
    {
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];
        $first = $tokens[0] ?? '';

        return trim((string) $first, "* \t\n\r\0\x0B\"");
    }

    private function plain(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }
}
