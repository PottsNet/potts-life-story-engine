# Potts Biography

Potts Biography is a privacy-aware individual-page tab for webtrees. It transforms the genealogy records visible to each visitor into a readable, illustrated life story with chapters, ages, historical context, research notes and intelligently placed media.

**Release:** 1.0.0-rc.6  
**Compatibility:** webtrees 2.2.x  
**PHP:** the same supported PHP versions as the installed webtrees 2.2 release

The internal module folder and PHP namespace remain `potts_life_story_engine` and `PottsLifeStoryEngine` so existing installations can upgrade without creating a second module or losing their tab settings.

## Highlights

- Readable chapters generated from visible GEDCOM facts
- Preferred-name support using `NICK` or a starred known-as given name such as `Charles Henry Lyle* /Potts/`
- Age-aware event wording and media captions
- Country-aware historical context that can pivot after migration
- Research notes displayed with their supporting events
- Intelligent media placement with reliable date and age inheritance
- Curated photographs, documents and keepsakes
- Turning points, story highlights and chapter transitions
- Responsive phone, tablet, desktop and print layouts
- Read-only presentation that leaves editing in webtrees Facts and events
- No AI service, telemetry or external genealogy-data transfer

## Installation

1. Download the release asset ZIP. Do not use GitHub’s automatically generated “Source code” archives.
2. Extract the ZIP and copy the included `potts_life_story_engine` folder into `webtrees/modules_v4/`.
3. Open **Control panel → Modules → Individual page tabs**.
4. Enable **Potts Biography**.
5. Move it to the first position when it should be the default reading tab.
6. Keep **Facts and events** enabled for adding and editing genealogy records.

For upgrades and rollback instructions, see `INSTALL.md`.

## Recommended tab setup

- Potts Biography first
- Facts and events second
- Specialist research tabs after these

Administrators may optionally disable Album or Potts Fact Ages when Potts Biography already provides the desired visitor-facing presentation.


## Optional companion modules

Potts Biography has no hard dependency on another Potts module. It continues to generate biographies when the companion modules below are absent.

- **Potts Historical Facts 1.1.1 or later** supplies the Historical context section through a public provider. Biography respects whether the module is enabled, its enabled/default collections, visitor choices, language-matched CSV files and persistent custom data. Version 1.1.0 remains supported through a conservative compatibility reader.
- **Potts Fact Ages 1.0.1 or later** is recommended when both modules are enabled because it excludes Biography cards from its title-tile age enhancer.
- **Potts Relationship Context 0.1.1 or later** is recommended because it keeps the overall relationship summary while excluding Biography cards from fact-level label injection.
- **Potts Modern Theme** is optional. Biography includes its own responsive styling and should also work with standard webtrees themes.

## More PottsNet webtrees modules

Potts Biography is one of a growing collection of webtrees modules published by PottsNet. Each module is maintained and released separately, so administrators can install only the features they need.

### Presentation and storytelling

- [Potts Modern Theme](https://github.com/PottsNet/potts-modern-theme) — a modern, responsive heritage theme for webtrees 2.2.
- [Potts Family Books](https://github.com/PottsNet/potts-family-books) — creates and publishes family-history books with chapters, images and genealogy links.

### Research and historical context

- [Potts Historical Facts](https://github.com/PottsNet/potts-historical-facts) — displays sourced regional historical events alongside an individual’s lifetime.
- [Potts Fact Ages](https://github.com/PottsNet/potts_fact_ages) — adds age-at-event labels to facts, events and historical events.
- [Potts Relationship Context](https://github.com/PottsNet/potts_relationship_context) — shows how individuals and associated people are related to a selected reference person.

### Site administration and engagement

- [Potts SEO Helper](https://github.com/PottsNet/potts-seo-helper) — provides genealogy-focused metadata, sitemap and robots.txt support.
- [Potts On This Day Email](https://github.com/PottsNet/potts_on_this_day_email) — sends scheduled family-history anniversary emails.
- [Potts Admin Shortcuts](https://github.com/PottsNet/potts-admin-shortcuts) — adds convenient administrator links to the webtrees My page dashboard.

Browse all currently published projects on the [PottsNet GitHub repositories page](https://github.com/PottsNet?tab=repositories).

## Preferred names

The story uses the first available name in this order:

1. A given name ending in `*`, for example `Charles Henry Lyle* /Potts/`
2. GEDCOM `NICK`
3. First given name

The formal full name is retained for the first introduction. The asterisk is never displayed publicly.

## Media dating and placement

For the most reliable result:

- attach media to the fact it illustrates
- add a genuine media date where known
- use clear media titles
- avoid relying only on filenames for dates

Undated, unattached media remains visible in **Photographs and keepsakes** without an invented year or age.

## Privacy and security

Potts Biography uses webtrees records and native media rendering. It follows the visibility decisions already made by webtrees and does not create a separate genealogy database. It does not send genealogy data to AI services, analytics services or third-party APIs. See `PRIVACY.md` and `SECURITY.md`.

The standard custom-module version check may request `latest-version.txt` from the GitHub repository when an administrator opens module-management pages. No genealogy data is included in that request.

## Themes and devices

The module includes self-contained styling and is intended to work with Potts Modern Theme and standard webtrees themes. Before publishing 1.0.0, test at least one standard theme at phone, tablet and desktop widths using `TESTING.md`.

## Translation

All visitor-facing module text is passed through webtrees translation helpers. Translation contributors can add language files under `resources/lang/`. See `TRANSLATING.md`.

## Custom Module Manager

The module implements the webtrees custom-module version and update interfaces. For Custom Module Manager releases:

- upload the prepared release asset ZIP
- keep the top-level folder named `potts_life_story_engine`
- publish `latest-version.txt` in the repository root
- use semantic version tags such as `v1.0.0-rc.6` and `v1.0.0`

## Support

Report issues at the repository issue tracker. Include the webtrees version, module version, active theme, a privacy-safe GEDCOM extract and a screenshot where practical.

## Licence

See `LICENSE`.
