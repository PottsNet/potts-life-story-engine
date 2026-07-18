<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Media;
use Illuminate\Support\Collection;

use function e;
use function htmlspecialchars;
use function implode;
use function ucfirst;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Render biography media using the same MediaFile::displayImage() helper as
 * webtrees' core Album tab. This keeps MIME handling, privacy, gallery links,
 * external media and non-image fallbacks aligned with webtrees itself.
 */
final class MediaRenderer
{
    public function render(Media $media, string $title, string $kind, string $icon, ?int $year = null, ?int $age = null, string $subjectName = ''): string
    {
        $files = $media->mediaFiles();

        if ($files->isEmpty()) {
            return $this->documentCard($media, $title, $kind, $icon, $year, $age);
        }

        $label = $title !== '' ? $title : I18N::translate('Family record');
        $kind  = $kind !== '' ? $kind : 'document';

        // Use Media::displayImage(), as the core individual Media and Album
        // views do. It resolves the first genuinely displayable image before
        // falling back to a file-type icon.
        $native = $media->displayImage(360, 270, 'contain', [
            'class'    => 'img-thumbnail wt-album-tab-image potts-story-illustration__image',
            'loading'  => 'lazy',
            'decoding' => 'async',
            'srcset'   => '',
            'sizes'    => '(max-width: 430px) 92vw, (max-width: 760px) 44vw, 360px',
        ]);

        return '<figure class="potts-story-illustration potts-story-illustration--'
            . htmlspecialchars($kind, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<div class="potts-story-illustration__media potts-story-illustration__media--native">'
            . $native
            . '</div>'
            . '<figcaption><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
            . ($kind !== 'photograph' ? '<span>' . htmlspecialchars(ucfirst($kind), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>' : '')
            . $this->dateCaption($year, $age)
            . $this->appearanceCaption($title, $kind, $age, $subjectName)
            . '</figcaption>'
            . '</figure>';
    }

    private function documentCard(Media $media, string $title, string $kind, string $icon, ?int $year = null, ?int $age = null): string
    {
        $label = $title !== '' ? $title : I18N::translate('Family record');
        $kind  = $kind !== '' ? $kind : 'document';

        return '<figure class="potts-story-illustration potts-story-illustration--document">'
            . '<div class="potts-story-illustration__media">'
            . '<a class="potts-story-illustration__document-link" href="'
            . e($media->url())
            . '" aria-label="' . e($label) . '">'
            . '<span class="potts-story-illustration__document" aria-hidden="true">'
            . htmlspecialchars($icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span></a></div>'
            . '<figcaption><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
            . '<span>' . htmlspecialchars(ucfirst($kind), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . $this->dateCaption($year, $age)
            . '</figcaption></figure>';
    }
    private function appearanceCaption(string $title, string $kind, ?int $age, string $subjectName): string
    {
        if ($kind !== 'photograph' || $age === null || $age < 0 || $subjectName === '') {
            return '';
        }

        $text = mb_strtolower($title);
        $isGroupContext = preg_match('/\b(family|group|anniversary|reunion|class|school|gathering|wedding party)\b/u', $text) === 1;
        if (!$isGroupContext) {
            return '';
        }

        $sentence = $age === 0
            ? I18N::translate('%s appears in this photograph during the first year of life.', $subjectName)
            : I18N::translate('%s appears in this photograph at about %s years of age.', $subjectName, I18N::number($age));

        return '<small class="potts-story-illustration__context">'
            . htmlspecialchars($sentence, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</small>';
    }


    private function dateCaption(?int $year, ?int $age): string
    {
        if ($year === null && $age === null) {
            return '';
        }

        $parts = [];
        if ($year !== null) {
            $parts[] = (string) $year;
        }
        if ($age !== null) {
            $parts[] = I18N::translate('Age %s', I18N::number($age));
        }

        return '<small class="potts-story-illustration__date">'
            . htmlspecialchars(implode(' · ', $parts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</small>';
    }

}
