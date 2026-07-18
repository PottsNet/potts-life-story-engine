# Potts Biography 1.0.0-rc.6

This release candidate corrects a historical-context regression introduced while integrating Potts Historical Facts 1.1.1.

## Fixed

- Biography again changes historical country at a dated immigration, emigration or residence event.
- A visitor-selected Historical Facts collection no longer replaces the person’s country timeline across their whole life.
- For John Henry Potts, the historical context can now use United States events before his 1849 immigration and Australian events from 1849 onward.

## Fallback behaviour

When visible genealogy records do not identify a country, Biography uses the visitor’s selected Historical Facts collections or the site defaults.

## Companion releases

- Potts Historical Facts 1.1.1
- Potts Fact Ages 1.0.1
- Potts Relationship Context 0.1.1

No new companion-module update is required for this fix.

## Preserved

All optional integration, translation, multiple-family narrative and compatibility improvements from rc.5 are retained.

## Documentation

- The README now includes direct links to the other publicly available PottsNet webtrees modules.

