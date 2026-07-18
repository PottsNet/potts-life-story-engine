# Potts Biography 1.0 release-candidate checklist

Record the browser, theme, login state and tested individual for each failure.

## Installation and updates

- ZIP contains one top-level folder: `potts_life_story_engine`.
- Fresh installation appears as **Potts Biography**.
- Upgrade from Potts Life Story Engine preserves activation and tab order.
- Custom Module Manager reads the complete semantic version.
- `latest-version.txt` matches the release tag.
- Rollback to the previous ZIP works without data migration.

## Core behaviour

- Potts Biography loads without PHP warnings or fatal errors.
- Facts and events remains the editing workspace.
- Family-link records do not leak into Facts and events when Families is disabled.
- Biography footer gives clear instructions to use the Facts and events tab for editing, without relying on an unreliable direct-tab link.
- A forced or malformed-record failure produces a safe message and a concise server-log entry.

## Translation readiness

- Module-specific labels use the active webtrees language where translations exist.
- Singular and plural counts are correct.
- Long translated headings wrap without overflow.
- Names, places and user-written notes are not altered by module translations.

## Privacy and permissions

Test as signed-out visitor, ordinary member and editor:

- living/private people remain protected
- hidden facts and private notes do not appear
- protected media is not exposed
- shared notes follow webtrees permissions
- historical-source links do not disclose private record data
- server logs do not contain names, GEDCOM content or note text

## Names and narrative

- Starred known-as names are used and `*` is not displayed.
- `NICK` is used when no starred given name exists.
- Possessives, plurals, dates and years read naturally.
- Ages are omitted when dates are insufficient or contradictory.

## Media

- Event-linked media inherits the correct date and age.
- Media-specific dates override unrelated event dates.
- `CHAN/DATE` is never used as a photograph date.
- Another person’s wedding or anniversary does not become the subject’s own marriage media.
- Undated unattached media appears without a fabricated date.
- Duplicate links display once with the strongest context.
- Images open through native webtrees behaviour.
- Long captions and portrait/panoramic images remain contained.

## Notes

- Short and long notes remain inside their event cards.
- Long URLs, tables and images do not widen the page.
- Shared-note links and Markdown render through webtrees.

## Historical context

- Test a migrant with a dated immigration fact and recognised countries in both the birth and immigration places.
- Confirm historical items before the immigration year use the earlier country and items from the immigration year onward use the destination country.
- Repeat while a custom Historical Facts collection is selected in the browser and confirm the Biography country timeline still takes precedence.
- Test an individual whose visible places do not identify a country and confirm visitor-selected or site-default collections are used as the fallback.

- Events stay within the person’s lifespan.
- Country changes follow dated migration or strong residence evidence.
- Northern Ireland maps to the United Kingdom collection.
- Biography still works when Potts Historical Facts is absent or disabled.
- Historical context appears only while Potts Historical Facts is enabled.
- Potts Historical Facts 1.1.1 supplies enabled/default collections, visitor choices, language-matched files and persistent custom CSV data.
- Potts Historical Facts 1.1.0 remains usable through the compatibility reader.

## Companion-module compatibility

- Potts Fact Ages 1.0.1 does not add duplicate age labels inside Biography cards.
- Potts Relationship Context 0.1.1 keeps its overall summary but does not inject fact labels inside Biography cards.
- Disabling either companion module does not change Biography generation.

## Themes and responsive layout

Test Potts Modern Theme plus at least Xenea and one other standard webtrees theme.

Widths:

- 320–375 px phone
- 768–834 px tablet
- 1280 px desktop
- wide desktop
- print preview

Confirm:

- no horizontal overflow
- header stacks correctly
- navigation, badges and summaries remain readable
- galleries use sensible columns
- touch controls are usable
- keyboard focus remains visible
- colours and links maintain readable contrast

## Performance and tolerance

Test biographies with about 50, 150 and 500 visible records, plus:

- no media
- extensive media
- duplicate media links
- missing dates and places
- invalid or partial dates
- very long notes
- external media and non-image documents

Confirm the page remains responsive and repeated media does not trigger excessive work.

## Release decision

Publish 1.0.0 only when no privacy, fatal-error, incorrect-date, incorrect-age, incorrect-media-placement or upgrade blocker remains. Record cosmetic enhancements for 1.0.1 or 1.1.0.

## Multiple-family narrative regression

Test an individual with two or more spouse families and children in each family. Confirm that:

- every visible spouse is represented in the opening summary and family chapter;
- each child count belongs to the correct family;
- the total number of unique children is stated separately;
- the chapter title uses “Marriages and family” where appropriate; and
- the narrative does not say that the first spouse had all children.
