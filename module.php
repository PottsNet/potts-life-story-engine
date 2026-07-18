<?php

declare(strict_types=1);

namespace PottsLifeStoryEngine;

require __DIR__ . '/src/TimelineFactFilter.php';
require __DIR__ . '/src/HistoricalContextItem.php';
require __DIR__ . '/src/HistoricalFactsProvider.php';
require __DIR__ . '/src/HistoricalContextBuilder.php';
require __DIR__ . '/src/StoryProfile.php';
require __DIR__ . '/src/StoryNote.php';
require __DIR__ . '/src/NoteExtractor.php';
require __DIR__ . '/src/MediaClassification.php';
require __DIR__ . '/src/MediaContext.php';
require __DIR__ . '/src/MediaContextResolver.php';
require __DIR__ . '/src/MediaPlacementResult.php';
require __DIR__ . '/src/MediaPlacementEngine.php';
require __DIR__ . '/src/MediaClassifier.php';
require __DIR__ . '/src/MediaRenderer.php';
require __DIR__ . '/src/StoryEvent.php';
require __DIR__ . '/src/StoryGroup.php';
require __DIR__ . '/src/StoryChapter.php';
require __DIR__ . '/src/EventClassifier.php';
require __DIR__ . '/src/NameResolver.php';
require __DIR__ . '/src/NarrativeBuilder.php';
require __DIR__ . '/src/LifeStory.php';
require __DIR__ . '/src/LifeStoryBuilder.php';
require __DIR__ . '/PottsLifeStoryEngine.php';

return new PottsLifeStoryEngine();
