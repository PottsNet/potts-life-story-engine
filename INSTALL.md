# Installation, upgrade and rollback

## New installation

1. Confirm webtrees 2.2.x is operating normally.
2. Download the prepared release asset ZIP rather than GitHub’s automatic source archive.
3. Extract the ZIP.
4. Copy the included `potts_life_story_engine` folder to `webtrees/modules_v4/`.
5. Open **Control panel → Modules → Individual page tabs**.
6. Enable **Potts Biography**.
7. Move Potts Biography above Facts and events when it should open first.

## Upgrade from Potts Life Story Engine

The public name changed to **Potts Biography**, but the internal folder and module identity are deliberately unchanged.

1. Keep a copy of the currently working module ZIP.
2. Replace the complete `potts_life_story_engine` folder with the new version.
3. Do not install a second folder named `potts_biography`.
4. Clear the webtrees cache when styling or templates appear unchanged.
5. Open several representative biographies and confirm media, notes, links and privacy behaviour.

Existing module activation and tab-order settings should continue because the internal module identity has not changed.

## Custom Module Manager

When the release is listed in Custom Module Manager, use its normal install or upgrade action. The GitHub release asset must contain exactly one top-level folder named `potts_life_story_engine`.

## Rollback

Replace the module folder with the previous working version. The module does not create a separate database schema or genealogy-data store, so no data migration is required.

## Optional companion updates

For the cleanest combined presentation, use Potts Historical Facts 1.1.1, Potts Fact Ages 1.0.1 and Potts Relationship Context 0.1.1 or later. These remain separate modules and should be installed from their own release ZIP files. Potts Biography continues to work when any of them are absent.
