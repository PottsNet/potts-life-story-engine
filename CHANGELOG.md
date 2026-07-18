# 1.0.0-rc.6 — Migration Country Timeline Fix

- Restores country-aware historical context as the primary selection rule whenever dated places identify a person's countries of residence.
- Changes historical collections at the dated immigration, emigration or residence pivot instead of applying one visitor-selected collection across the entire lifetime.
- Uses visitor-selected or site-default Historical Facts collections only when no country can be inferred from visible genealogy records.
- Retains the optional Potts Historical Facts provider, translation loading and companion-module compatibility introduced in rc.5.
- Expands the README with links to the other publicly available PottsNet webtrees modules.

# 1.0.0-rc.5 — Optional Integration and Compatibility

- Uses Potts Historical Facts only when that module is installed and enabled.
- Adds a public-provider integration for Potts Historical Facts 1.1.1 while retaining compatibility with 1.1.0.
- Reads visitor/site collection choices, language-matched CSV files and persistent custom historical data through the companion module.
- Keeps country-aware historical context and migration pivots without making Historical Facts a hard dependency.
- Adds working PHP translation-file loading from `resources/lang/`.
- Documents compatible companion releases for Potts Fact Ages and Potts Relationship Context.
- Retains all multiple-family narrative fixes from rc.4.

# 1.0.0-rc.4 — Multiple-Family Narrative Intelligence

- Rebuilds the opening life summary from every visible spouse family instead of only the first marriage.
- Reports the number of children belonging to each relationship rather than assigning the total child count to the first spouse.
- Adds a separate total-parenthood sentence when more than one family is recorded.
- Uses factual wording such as “were the parents of” instead of assuming that both partners raised every child.
- Changes the chapter heading to “Marriages and family” when multiple family relationships are represented.
- Keeps the opening summary and the family chapter consistent by using the same family relationship model.
- Handles spouse families without a recorded marriage using neutral relationship wording.
- Orders recorded marriages chronologically for a clearer life-story sequence.

# 1.0.0-rc.3 — Multiple Marriage and Family Narrative Fix

- Builds the Marriage and family introduction from every visible spouse family rather than only the first marriage.
- Reports the number of children belonging to each relationship separately.
- Prevents the total number of children across multiple spouses from being attributed to the first spouse.
- Retains a total unique-child count for the At a glance summary.
- Handles spouse families without a recorded marriage fact using neutral family wording.

# 1.0.0-rc.2 — Editing Guidance Fix

- Removes the unreliable footer button that attempted to activate the Facts and events tab directly.
- Replaces it with clear, translation-ready guidance telling editors to select the Facts and events tab near the top of the individual page.
- Retains the read-only Biography presentation and all release-candidate compatibility, privacy and stabilisation work.

# 1.0.0-rc.1 — Potts Biography Release Candidate

- Renamed the public module title from Potts Life Story Engine to Potts Biography while retaining the internal folder, namespace and module identity for upgrade compatibility.
- Completed translation-readiness review and added contributor guidance.
- Added privacy and security documentation.
- Added safe biography-generation error handling with privacy-conscious server logging.
- Added cross-theme, responsive and print CSS safeguards.
- Expanded phone, tablet, desktop, theme, permission, performance and malformed-data regression checks.
- Reworked README and installation, upgrade, rollback and Custom Module Manager instructions.
- Removed alpha-era public wording and aligned version metadata for the 1.0 release candidate.

# 0.9.7-rc.1 — Release Candidate Preparation

- Prepared release-ready README, installation and upgrade guidance.
- Added a structured regression-test checklist for names, media, notes, privacy, historical context and responsive layouts.
- Documented compatibility with webtrees 2.2.x and rollback procedures.
- Clarified privacy behaviour and confirmed that no genealogy data is sent to external services.
- Retained the stable 0.9.6 feature set with no new story-generation behaviour.

# 0.9.6-alpha.2 — Research Note Containment Fix

- Prevents long note text, links and native webtrees note fields from escaping an event card.
- Adds safe wrapping for long words, URLs and unbroken text.
- Constrains images, tables, code blocks and embedded content to the available note width.
- Neutralises negative Bootstrap row gutters inside native note output.
- Retains the stabilisation, media placement and narrative improvements from 0.9.6-alpha.1.

# 0.9.6-alpha.1 — Stabilisation and Polish

- Added deterministic media-date candidate ranking.
- Deprioritised inferred dates that fall outside the person’s recorded lifetime.
- Added request-level media-context caching to avoid repeated resolution work.
- Prevented standalone NOTE records from appearing as timeline events while preserving event-note integration.
- Clarified administration guidance for reliable media dates and undated keepsakes.
- Retained all 0.9.5 media placement, age-caption and family-event safeguards.

# 0.9.5-alpha.11 — Event Media Age Caption Fallback

- Restores a media item's linked fact year when the resolved media year is missing at render time.
- Calculates the subject's age from the linked fact date as a final safety net.
- Preserves authoritative resolver dates and ages whenever they are available.

# 0.9.5-alpha.10 — StoryEvent Constructor Hotfix

- Fixes the fatal type error introduced in alpha.9 when ordinary facts were classified.
- Supplies the new media date source and confidence fields in the correct constructor positions.
- Preserves research notes as the final StoryEvent argument.
- Keeps the media context handoff and de-duplication improvements from alpha.9.

# 0.9.5-alpha.9 — Media Context Handoff Fix

- Preserves the resolved media date, age, date source and confidence through chapter placement and rendering.
- Fixes duplicated media links where an undated individual-level copy could replace the same media attached to a dated event.
- De-duplication now prefers explicit media dates and linked-event dates before general placement confidence.
- Restores captions such as `December 1989 · Age about 23` after media is moved to its most suitable biography chapter.
- Keeps all existing media placement safeguards and no-duplication behaviour.

# 0.9.5-alpha.8 — Media Context Resolver

- Added a dedicated `MediaContextResolver` and immutable `MediaContext` result.
- Media date selection is now performed once and shared by placement and age captions.
- Date priority is: media DATE, linked event DATE, media title, file title, filename, media note.
- A filename year can no longer override a reliable dated event.
- Administrative `CHAN/DATE` values remain excluded.
- Resolver reasons and confidence are retained for future diagnostics.
- Undated unattached media receives no invented date or age.

# 0.9.5-alpha.7 — Other People's Wedding Safeguard

- Stops filename-only words such as `wedding` from being treated as evidence of the subject's own marriage.
- Compares a photograph's reliable date with the subject's recorded marriage dates.
- Moves photographs from another person's wedding to Photographs and keepsakes when the dates do not match.
- Uses media-only wording for contextual checks so the linked fact cannot contaminate the decision.

# 0.9.5-alpha.6 — Media Date Provenance Fix

- Fixed a regression where a media record's `CHAN/DATE` edit timestamp could be interpreted as the photograph date.
- Removed raw media GEDCOM from inferred-year scanning.
- Preserved explicit media dates, title/filename/note years and linked-event dates in the intended priority order.
- Restored correct age captions for undated media attached to dated events.

# 0.9.5-alpha.5 — Childhood Family Photo Override

- Applies age-based contextual correction to all media links, including generic `EVEN Family Photo` facts.
- Recognises parents’ silver, golden and diamond wedding anniversaries even when the media is not linked to a marriage fact.
- Prevents photographs taken before age 16 from entering the subject’s own Marriage and family chapter solely because titles contain wedding, anniversary or family wording.
- Places childhood family-group photographs in Early years while preserving the original GEDCOM link as evidence.

# 0.9.5-alpha.5 — Reliable Media Dates and Age Priority

- Prioritises media-owned dates and years over the date of an incorrectly linked event.
- Uses age contradictions as a hard placement safeguard.
- Stops scanning linked fact GEDCOM when extracting a year from media metadata.
- Keeps undated, unattached media in Photographs and keepsakes without a fabricated year or age.
- Retains dated standalone media events as valid timeline evidence.

# 0.9.5-alpha.3 — Contextual Media Correction

- Prefer media DATE and years in media metadata over the linked fact date.
- Make strong contextual contradictions authoritative for narrative placement.
- Correct childhood photographs from parents' anniversaries that were attached to the subject's own marriage fact.
- Preserve the original GEDCOM association as supporting evidence without allowing it to force the wrong chapter.

# 0.9.5-alpha.2 — Narrative Placement Intelligence

- Added conservative contextual overrides for media attached to the wrong life event.
- Recognises parents’ silver, golden, diamond and general wedding-anniversary photographs.
- Prevents childhood photographs from being presented as the subject’s own marriage evidence.
- Adds contextual age captions to family, group, school, reunion and anniversary photographs.
- Adds relative-time chapter bridges for closely spaced life stages.
- Retains explicit GEDCOM links as supporting evidence while allowing strong contradictory context to choose the correct story chapter.

# 0.9.5-alpha.1 — Age-aware Storytelling

- Added age-at-event captions to dated biography event cards.
- Added age context to marriage, education, migration and career chapter narratives.
- Added age context to turning-point summaries where reliable dates exist.
- Uses webtrees’ `Age` calculation and suppresses invalid or implausible ages.
- Retained the responsive Biography layout, intelligent media placement and Facts and events separation.

# 0.9.4-alpha.3 — Turning Point Link Contrast

- Darkens the linked chapter headings inside the **Turning points** cards for clearer readability.
- Keeps visited links the same dark biography heading colour instead of allowing browser or theme link styles to fade them.
- Adds a clear accent-colour hover and keyboard-focus state with an underline.
- Retains the existing year, descriptive text and chapter accent colours.

# 0.9.4-alpha.2 — Key Moments and Story Emphasis

- Gives major life events a discreet **Key moment** label.
- Adds stronger visual emphasis to birth, migration, marriage, military service, divorce, wills and death without changing chronology.
- Renames grouped **Featured record** labels to **Story highlight** so supporting evidence reads more naturally in the biography.
- Keeps standard and minor records visually quieter, helping readers distinguish the shape of the life from the underlying evidence.
- Includes responsive badge sizing for phones and tablets.

# 0.9.4-alpha.1 — Turning Points and Story Highlights

- Adds a selective **Turning points** panel near the opening of the Biography.
- Identifies major changes such as migration, marriage, military or public service, defining career work, retirement and death.
- Uses the person’s preferred narrative name in turning-point summaries.
- Links each turning point directly to its relevant biography chapter.
- Keeps the feature intentionally concise so it does not duplicate the full milestone strip or chapter navigation.
- Provides a swipeable, touch-friendly presentation on phones.

# 0.9.3-alpha.3 — Possessive Wording and Facts Tab Link Fix

- Corrects preferred-name chapter wording, for example “The beginnings of Lyle’s recorded story.”
- Corrects fallback early-years wording to use the possessive form of the preferred narrative name.
- Updates the Biography footer link to use webtrees’ required `#tab-personal_facts` fragment so the Facts and events tab is activated rather than merely scrolling the page.

# 0.9.3-alpha.2 — Biography Intelligence and Name Guidance

- Renames the opening insight panel to **At a glance** and retains the strongest automatically generated life highlights.
- Adds a preferred-name assistant to the administration page.
- Documents the supported name priority: starred given name, `NICK`, then first given name.
- Uses the cleaned formal name in the Biography heading and the preferred narrative name in story text and historical context.
- Builds the Facts and events footer link from the individual URL and appends `#personal_facts` reliably.

# 0.9.3-alpha.1 — Identity & Names

- Detects the preferred “known as” given name when a token ends in `*` in the primary GEDCOM `NAME` record.
- Falls back to `NICK`, then the first given name.
- Uses the formal name for the first introduction and the preferred narrative name afterwards.
- Removes the private asterisk marker from all public biography text.
- Uses cleaned formal names for spouses.

## 0.9.2-alpha.2

- Removes standalone source-citation pseudo-events such as `Source citation @S2@` from the Biography timeline.
- Keeps source references attached to genuine facts and events available through their normal supporting records.
- Corrects the footer link so **View or edit the Facts and events** opens the core `personal_facts` tab.

# Changelog

## 0.9.2-alpha.1 — Research Notes Integration

- Reads visible inline and shared notes attached to individual and family events.
- Shows short notes directly with the event they explain.
- Places longer notes in an expandable Research notes section to keep biographies readable.
- Uses webtrees’ native note renderer so links, Markdown, privacy and shared-note behaviour remain consistent with core webtrees.
- Keeps Biography read-only; notes continue to be added and edited in Facts and events.
- Adds administration guidance explaining how event notes appear in Biography.

## 0.9.1-alpha.3 — Refined transitions and chapter stages

- Removed locale thousands separators from all years used in narrative transitions.
- Added varied transition openings based on chapter, date and story profile.
- Added book-like life-stage labels above chapter titles.
- Replaced prominent raw event counts with quieter supporting-event metadata.
- Avoided unnecessary generic transitions where they do not improve the story flow.

## 0.9.1-alpha.2 — Story transitions

- Adds short narrative bridges between consecutive biography chapters.
- Uses the next chapter’s earliest usable year where available.
- Adapts transition wording for teaching, military, farming and general life stories.
- Improves reading flow without changing facts, media placement or the Facts and events tab.
- Keeps transitions compact and mobile friendly.

# 0.9.0-alpha.4 — Timeline fact filter

## 0.9.1-alpha.2 — Rich chapter openings

- Adds short, profile-aware chapter subtitles that read like book section introductions.
- Gives each chapter narrative a clearer, publication-style opening panel.
- Adds career-specific subtitle language for teachers, military service, farming, mining, faith and public service profiles.
- Improves reading rhythm and visual separation between narrative, media and supporting evidence.
- Keeps the responsive phone layout and existing media-placement engine unchanged.


## 0.9.0-alpha.5 — Family-link suppression fix

- Declares `INDI:FAMC`, `INDI:FAMS`, `FAM:HUSB`, `FAM:WIFE` and `FAM:CHIL` as handled by Biography.
- Prevents structural family-link records such as “Family as a spouse”, “Family as a child”, “Husband”, “Wife” and “Child” from appearing in the core Facts and events tab when the standard Families tab is disabled.
- Keeps the Biography family navigator and narrative family information unchanged.

- Added a central `TimelineFactFilter` as the single authority for excluding structural GEDCOM records from biography processing.
- Explicitly excludes `FAMC`, `FAMS`, `HUSB`, `WIFE`, `CHIL`, housekeeping identifiers and change records.
- Applied the filter to biography fact collection and timeline-based media matching.
- Prevents family relationship structures from being interpreted as life events by the Biography engine.
- Retains genuine spouse-family events such as marriage, engagement, divorce and annulment.

## 0.9.0-alpha.3 — Curated story layout

- Places chapter media immediately after the chapter narrative.
- Selects the strongest event-related photograph as the chapter hero image.
- Shows up to three supporting photographs in a compact gallery.
- Separates documentary records from photographs.
- Adds a clear visual transition into key events and supporting records.
- Preserves age/year captions, native webtrees media rendering and click-to-enlarge behaviour.
- Retains the responsive phone layout and no-duplication rules.

## 0.9.0-alpha.2 — Timeline intelligence

- Compares effective media dates with nearby dated life events.
- Gives stronger placement weight to exact and near-year matches for marriage, education, career, migration, service and final-years events.
- Avoids ambiguous timeline matches when two different chapters are equally plausible.
- Calculates age at the effective media year.
- Adds year and age captions beneath biography media where dates are available.
- Retains the adult christening/baptism safeguard and the Photographs and keepsakes fallback.

## 0.9.0-alpha.1 — Placement engine foundation

- Replaced hard-coded media chapter assignment with a weighted scoring engine.
- Added effective media-year extraction from linked facts, media dates, titles, filenames and notes.
- Explicit GEDCOM event links now receive the highest placement priority.
- Added timeline sanity checks so adult christening or baptism photographs are not treated as early-years material unless explicitly linked to that event.
- Added a safe Photographs and keepsakes fallback for weak or ambiguous matches.
- Retained single-placement and no-duplication behaviour.
- Stored chapter scores, confidence, effective year and placement reasons internally for future administrator diagnostics.

## 0.8.2-alpha.2 — Intelligent media placement

- Adds a formal confidence-based media placement hierarchy.
- Media explicitly linked to a recognised life event remains with that event.
- Strongly labelled standalone media, such as wedding, baptism, school, military or funeral material, is assigned to the matching chapter.
- Generic timeline photographs, portraits, letters and keepsakes now move to Photographs and keepsakes instead of being forced into the nearest chapter.
- Removes broad family-keyword matching that could incorrectly move ordinary family photographs into Marriage and family.
- Keeps each media object in one best location to prevent duplication.
- Retains native webtrees rendering, privacy controls, curated galleries and responsive layouts.

## 0.8.2-alpha.1 — Story-first chapter layout

- Places chapter photographs and documents immediately after the chapter narrative and before supporting facts.
- Keeps media linked directly to an event with that event's chapter unless a stronger explicit match exists.
- Improves handling of photographs attached to marriage, baptism, education, occupation and other associated event records.
- Retains curated featured media, separate photographs and documents and no-duplication rules.
- Removes redundant “Photograph” labels beneath image captions.
- Adds visual hierarchy refinements for featured images, documentary records and supporting evidence.

# Changelog

## 0.8.1-alpha.2 — Northern Ireland migration pivot

- Recognises `Northern Ireland` and `N Ireland` in GEDCOM place names.
- Uses the United Kingdom historical collection for the Northern Irish period.
- Pivots to the destination country from the dated immigration or first strong residence record.
- For year-only migration dates, the destination country begins in the migration year.


## 0.8.1-alpha.1 — Country-aware historical context

- Builds a dated country-of-residence timeline from visible birth, immigration, residence, census, marriage, occupation, education, retirement, death and burial facts.
- Uses the matching Potts Historical Facts country collection for each period of the person’s life.
- Pivots historical context when the recorded country changes, such as Ireland to Australia after immigration.
- Keeps world-history events available across every country period.
- Reserves historical context from each recorded country where suitable events exist.
- Labels historical events with the country collection that supplied them.
- Falls back to the visitor’s selected historical collection when no country can be inferred.

## 0.8.0-alpha.1 — Responsive Biography

- Rebuilt the biography header as a true single-column phone layout.
- Prevented later desktop theme rules from restoring a squeezed two-column header on narrow screens.
- Centred the portrait, name, lifespan and life badges on phones.
- Made occupation badges wrap naturally instead of collapsing into narrow vertical pills.
- Added horizontally scrollable life statistics, milestones and chapter navigation.
- Stacked the biography and sidebar areas cleanly on phones.
- Improved mobile event cards, grouped records, media galleries and touch targets.
- Added two-column phone galleries with a one-column layout on very narrow screens.
- Added reduced-motion support and stronger overflow protection.

## 0.7.0-alpha.15

- Added a curated chapter-media layout with one featured photograph and up to three supporting photographs shown initially.
- Separated photographs from documents and records inside each chapter.
- Collapsed additional photographs and documents behind clear “View more” controls.
- Kept title-only media references in a separate related-records section.
- Improved media classification so baby, birth, christening and baptism material is assigned to Early years before broad family matching is considered.
- Preserved native webtrees media rendering, privacy and click-to-enlarge behaviour.

## 0.7.0-alpha.14

- Resolves nested `OBJE` links inside events before classifying and rendering media.
- Converts descriptive media facts such as “Family Photo” into their actual linked Media objects.
- Prevents duplicate title-only media cards when a usable linked media object exists.
- Uses webtrees’ native `Media::displayImage()` selection so the first genuinely displayable file is chosen.
- Keeps media privacy, click-to-enlarge behaviour and chapter placement intact.

# Changelog

## 0.7.0-alpha.13

- Prioritises media records with attached displayable files in chapter galleries.
- Moves title-only PHOTO, LETTER and similar facts into a compact related-media-records section.
- Prevents generic record tiles from appearing ahead of real photographs.
- Refines single-image sizing while retaining natural aspect ratios and native webtrees rendering.

## 0.7.0-alpha.12

- Removed the fixed 4:3 frame from biography media cards.
- Media now follows its natural aspect ratio, including panoramic photographs and tall documents.
- Increased the native webtrees preview request to 360 × 270 while retaining a single preview source.
- Single illustrations can use a wider card while multi-item galleries remain compact and responsive.
- Preserved webtrees click-to-enlarge behaviour and mobile layouts.

## 0.7.0-alpha.11

- Increased native Album-style previews from 100 × 100 to 180 × 135 pixels.
- Replaced full-width single-image frames with compact media cards.
- Kept chapter galleries responsive with two columns on phones and one column on very narrow screens.
- Preserved webtrees click-to-enlarge behaviour and native media handling.

## 0.7.0-alpha.10

- Replaced custom biography preview logic with webtrees core `MediaFile::displayImage()`, matching the Album tab.
- Preserved webtrees gallery links, MIME handling, privacy and external-media behaviour.
- Limited each media file to one compact 100 × 100 core thumbnail request by suppressing 2×, 3× and 4× `srcset` variants.
- Removed the custom JavaScript image-fallback handler.
- Added layout support for media objects containing multiple files.

## 0.7.0-alpha.9

- Stopped using the server-side `media-thumbnail` route in Biography chapters.
- Streams protected original media files directly and scales them in the browser.
- Added lazy loading and asynchronous decoding to reduce initial page load.
- Avoids HTTP 508 resource-limit failures caused by bursts of thumbnail generation.
- Keeps click-to-enlarge gallery behaviour and clear fallbacks for unsupported formats.

## 0.7.0-alpha.8

- Replaced direct original-image previews with webtrees' native thumbnail renderer for standard images.
- Disabled high-density `srcset` variants to avoid inconsistent 2x/3x/4x thumbnail failures.
- Added support for legacy media records whose `FILE` value has no extension but whose `FORM` or `TYPE` identifies an image.
- Falls back once from the thumbnail to the protected original image before showing a clear unavailable tile.
- Keeps all previews compact and click-to-enlarge.

## 0.7.0-alpha.7

- Added a dedicated central media renderer.
- Tries the protected original image first and the signed webtrees thumbnail second.
- Replaces failed previews with an informative file tile instead of a blank holder.
- Recognises common image formats, including HEIC/HEIF/TIFF candidates, while preserving click-to-open behaviour.
- Keeps all media rendering logic out of the biography template for easier maintenance.

## 0.7.0-alpha.6

## 0.7.0-alpha.6

- Added resilient thumbnail previews with an original-image fallback.
- Prevented the same media object from appearing in multiple chapters.
- Renamed chapter media groups according to their contents: Photographs, Illustrations and records, or Photographs and records.


- Fixed the Biography administration page by using the registered webtrees control-panel route.
- Replaced generated thumbnail URLs with the protected original-media download route for reliable image loading.
- Retained compact thumbnail presentation, click-to-enlarge gallery behaviour and external-media support.

## 0.7.0-alpha.4

- Fixed biography thumbnails for externally hosted media and mixed media storage.
- Switched thumbnail rendering to webtrees' native `MediaFile::displayImage()` method.
- Retained the standard webtrees gallery viewer when a thumbnail is selected.
- Preserved compact thumbnail sizing and responsive phone layouts.

## 0.7.0-alpha.3

- Replaced responsive `srcset` thumbnail generation with a single reliable signed thumbnail URL.
- Kept thumbnails clickable through the standard webtrees full-image gallery viewer.
- Added a module administration guidance page with recommended individual-tab setup.
- Recommended placing Biography first while retaining Facts and events for editing and research.
- Documented optional removal of duplicate Potts Fact Ages and Album tabs.

# 0.7.0-alpha.2

- Reworked biography media as compact thumbnails.
- Uses webtrees' built-in gallery link so thumbnails open the full media viewer.
- Removed invalid nested links around generated image markup.
- Reduced thumbnail requests from 720 × 480 to 240 × 180 for faster and more reliable loading.
- Removed lazy-loading from visible biography thumbnails so full-page captures and long-page views do not leave blank placeholders.
- Constrained generated gallery links and images so media cannot expand beyond its card.
- Retained a document placeholder linked to the media record for non-image files.
- Improved two-column phone layout with a single-column fallback on very narrow screens.

# 0.7.0-alpha.1

- Introduced the first read-only illustrated Biography presentation.
- Display linked media objects inside the chapters they support.
- Added responsive chapter galleries for photographs, newspapers, letters, certificates and documents.
- Added a discreet link back to the standard Facts and events tab for research and editing.
- Added phone-first layouts, horizontal milestone navigation and larger touch targets.
- Preserved webtrees privacy controls and the original GEDCOM records.

## 0.6.4-alpha.1

- Added a dedicated media-classification layer for photographs, newspaper items, letters, certificates, documents and keepsakes.
- Automatically associates media-like records with the most relevant biography chapter, including family, education, career, service, homes, migration, early years and final years.
- Added chapter-level “Illustrations and records” groups when multiple related media items are available.
- Prevented recognised media records from falling into “Other parts of the story” unnecessarily.
- Added internal media kind, target chapter and confidence metadata to prepare for illustrated chapter layouts in a later release.
- Preserved generic or uncertain media in the Photographs and keepsakes chapter.

## 0.6.3-alpha.1

- Added richer, human-readable summaries for grouped career, residence, education, service and keepsake records.
- Improved date-range calculation so grouped periods use the earliest and latest recorded years.
- Improved featured-record selection using event detail, place, date and chapter-specific relevance.
- Added clearer wording for short and long careers, mixed roles, multiple residences and educational stages.
- Preserved all supporting records inside expandable groups.

## 0.6.2-alpha.3

### Fixed
- Removed sticky positioning from the chapter navigation so it no longer overlaps chapter headings or event cards.
- Kept chapter links within the biography content column.
- Improved wrapping and spacing for tablet and mobile layouts.
- Preserved adaptive story profiles, chapter ordering and anchor navigation.

# Changelog

## 0.6.2-alpha.2

- Fixed an undefined StoryProfile variable when creating grouped chapter titles.
- Passed the detected adaptive story profile through the grouping pipeline.
- Preserved adaptive career naming and chapter ordering without changing visible GEDCOM facts.

## 0.6.2-alpha.1

- Added adaptive story profiles for educators, military service, farming, mining, faith, migration and public life.
- Added dynamic chapter ordering so the biography emphasises the most important themes in each person’s life.
- Added profile-aware career and community narratives.
- Added a subtle Story focus label to the biography introduction.
- Added profile-specific “Did you know?” insights while preserving all visible GEDCOM facts.


## 0.6.1-alpha.1

- Added event importance scores for major, supporting and minor records.
- Added featured records to grouped career, residence, education, service and keepsake cards.
- Added an optional cover quotation drawn from suitable visible notes.
- Improved story hierarchy without changing GEDCOM data or privacy behaviour.


## 0.6.0-alpha.2

- Improved historical-context ranking using the individual’s visible places and occupations.
- Reduced the initial historical display to three events with an expandable remainder.
- Added broader topic de-duplication so overlapping descriptions of the same war or social change are not repeated.
- Replaced technical age labels with person-centred wording.
- Added internal chapter associations to historical events for future in-story placement.
- Refined the historical-context heading and explanatory text.

## 0.6.0-alpha.1

- Added historical context integration with the installed Potts Historical Facts module.
- Reads the visitor’s selected historical collection where available.
- Filters historical events to the individual’s recorded lifetime.
- Selects a concise set of significant events and displays the person’s approximate age.
- Includes source links and gracefully hides the panel when Potts Historical Facts is unavailable.

## 0.5.0-alpha.1

- Added a richer rule-based Narrative Composer with more natural biography prose.
- Added gender-aware pronouns and reduced repeated use of the person’s full name.
- Reworked opening biographies to connect birth, career, marriage, family and final years more naturally.
- Improved chapter introductions for early years, education, careers, residences and family life.
- Replaced technical milestone labels with human-readable life stages such as Born, Married, Career, Retired and Died.
- Improved “Did you know?” insights and grouped-record summaries.
- Removed preview wording from the Biography presentation.
- Prepared the narrative structure for future chapter-based media placement.

## 0.4.0-alpha.1

- Added a key life milestones strip linking to relevant biography chapters.
- Added sticky chapter navigation for long biographies.
- Added deterministic “Did you know?” highlights based on visible career, residence, family, migration and service records.
- Improved the opening biography and chapter prose so it reads more naturally.
- Added a dedicated Photographs and keepsakes chapter for media-like custom events.
- Added expandable keepsake grouping and clearer “Show details” prompts.
- Improved marriage and family narrative text.

## 0.3.0-alpha.1

- Added a deterministic narrative engine that creates a readable biographical introduction from visible GEDCOM facts.
- Replaced technical chapter titles with more natural headings such as Early years, Marriage and family, Teaching career and Places called home.
- Added smarter career chapter naming based on recorded occupations.
- Rewrote chapter introductions in more natural language.
- Improved grouped career, residence, education and service summaries.
- Renamed the individual tab from Life story to Biography.

## 0.2.0-alpha.2

- Fixed class loading for grouped story objects.
- Added smart grouping for repeated occupations, residences, education and military records.
- Added chapter introductions and expandable grouped event cards.

## 0.1.0-alpha.2

- Fixed module booting before a PSR-7 request is available.
- Added the first chapter-based life story view.
