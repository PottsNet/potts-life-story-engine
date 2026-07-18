<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Support\Collection;
use Throwable;

use function view;

final class PottsLifeStoryEngine extends AbstractModule implements ModuleTabInterface, ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleTabTrait;
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    private const VERSION = '1.0.0-rc.6';

    private MediaClassifier $mediaClassifier;
    private EventClassifier $classifier;
    private LifeStoryBuilder $builder;
    private NarrativeBuilder $narrativeBuilder;
    private NameResolver $nameResolver;
    private HistoricalContextBuilder $historicalContextBuilder;
    private MediaPlacementEngine $mediaPlacementEngine;
    private MediaContextResolver $mediaContextResolver;
    private TimelineFactFilter $timelineFactFilter;
    private NoteExtractor $noteExtractor;

    public function __construct()
    {
        $this->timelineFactFilter = new TimelineFactFilter();
        $this->mediaClassifier = new MediaClassifier();
        $this->noteExtractor = new NoteExtractor();
        $this->classifier = new EventClassifier($this->mediaClassifier, $this->noteExtractor);
        $this->nameResolver = new NameResolver();
        $this->narrativeBuilder = new NarrativeBuilder($this->nameResolver);
        $this->historicalContextBuilder = new HistoricalContextBuilder(new HistoricalFactsProvider());
        $this->mediaContextResolver = new MediaContextResolver();
        $this->mediaPlacementEngine = new MediaPlacementEngine($this->timelineFactFilter, $this->mediaContextResolver);
        $this->builder = new LifeStoryBuilder($this->classifier, $this->narrativeBuilder, $this->historicalContextBuilder, $this->mediaPlacementEngine, $this->timelineFactFilter);
    }

    public function title(): string
    {
        return I18N::translate('Potts Biography');
    }

    public function description(): string
    {
        return I18N::translate('Creates an intelligent, illustrated biography from the genealogy records visible to each visitor.');
    }

    public function isEnabledByDefault(): bool
    {
        return false;
    }

    public function defaultTabOrder(): int
    {
        return 2;
    }

    /**
     * Family-link records are presented by the Biography and family navigator.
     * Declare them as supported so the core Facts and events tab does not expose
     * structural GEDCOM links when the standard Families tab is disabled.
     *
     * @return Collection<int,string>
     */
    public function supportedFacts(): Collection
    {
        return new Collection([
            'INDI:FAMC',
            'INDI:FAMS',
            'FAM:HUSB',
            'FAM:WIFE',
            'FAM:CHIL',
        ]);
    }

    public function hasTabContent(Individual $individual): bool
    {
        try {
            return $this->builder->build($individual)->eventCount() > 0;
        } catch (Throwable $exception) {
            $this->logFailure('hasTabContent', $exception);

            // Keep the tab available so getTabContent() can show a safe,
            // translated error message instead of silently hiding it.
            return true;
        }
    }

    public function isGrayedOut(Individual $individual): bool
    {
        return false;
    }

    public function canLoadAjax(): bool
    {
        return false;
    }

    public function getTabContent(Individual $individual): string
    {
        try {
            return view('potts-life-story::tab', [
                'classifier'   => $this->classifier,
                'individual'   => $individual,
                'story'        => $this->builder->build($individual),
                'nameResolver' => $this->nameResolver,
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('getTabContent', $exception);

            return '<div class="alert alert-warning" role="alert">'
                . e(I18N::translate('This biography could not be generated. The underlying genealogy records remain unchanged. Please ask the site administrator to review the server log.'))
                . '</div>';
        }
    }

    /** @return array<string,string> */
    public function customTranslations(string $language): array
    {
        $language = preg_replace('/[^A-Za-z0-9_-]/', '', trim($language)) ?? '';
        $language = str_replace('_', '-', $language);

        if ($language === '') {
            return [];
        }

        $base = strtolower(strtok($language, '-') ?: $language);
        $candidates = array_values(array_unique(array_filter([
            $base,
            $language,
            strtolower($language),
            str_replace('-', '_', $language),
        ])));

        $translations = [];

        foreach ($candidates as $candidate) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $candidate) !== 1) {
                continue;
            }

            $file = $this->resourcesFolder() . 'lang/' . $candidate . '.php';
            if (!is_file($file)) {
                continue;
            }

            $loaded = include $file;
            if (!is_array($loaded)) {
                continue;
            }

            foreach ($loaded as $source => $translation) {
                if (is_string($source) && is_string($translation)) {
                    $translations[$source] = $translation;
                }
            }
        }

        return $translations;
    }

    public function boot(): void
    {
        View::registerNamespace('potts-life-story', $this->resourcesFolder() . 'views/');

        // Module booting happens before a PSR-7 request is available in webtrees.
        // assetUrl() needs the current request to build a route, so calling it here
        // causes the container to try to instantiate ServerRequestInterface.
        // Inline the small stylesheet instead; this is safe during module boot and
        // keeps the tab available on normal and AJAX page loads.
        $css_file = $this->resourcesFolder() . 'css/life-story.css';
        $css = is_file($css_file) ? file_get_contents($css_file) : false;

        if (is_string($css) && $css !== '') {
            View::pushunique('styles');
            echo '<style data-potts-life-story-engine>' . $css . '</style>';
            View::endpushunique();
        }
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse('potts-life-story::admin', [
            'title'   => $this->title(),
            'version' => self::VERSION,
        ]);
    }

    private function logFailure(string $operation, Throwable $exception): void
    {
        // Avoid writing names, GEDCOM content, notes or media details to the log.
        error_log(sprintf(
            '[Potts Biography %s] %s failed with %s at %s:%d',
            self::VERSION,
            $operation,
            $exception::class,
            basename($exception->getFile()),
            $exception->getLine()
        ));
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    public function customModuleVersion(): string
    {
        return self::VERSION;
    }

    public function customModuleAuthorName(): string
    {
        return 'Jason Potts';
    }

    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/PottsNet/potts-life-story-engine/issues';
    }

    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/PottsNet/potts-life-story-engine/main/latest-version.txt';
    }
}
