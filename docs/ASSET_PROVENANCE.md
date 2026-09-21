# Asset provenance — Fantasy RPG and Digital-Mentoring (PA) scenarios

This file records where the artwork of the two original scenarios comes from and under which
licence it may be used. It describes **what was verified**, not what is assumed. An entry marked
*unresolved* is not a statement that the asset is unlicensed — only that the licence could not be
established from the file itself and has to be confirmed by its author.

Recorded: September 2026, from the backups `backup-fantasy_en.mbz` and
`backup-pa-instant-tutoring_en.mbz`.

---

## How the originals are built

Neither backup contains any asset. `files.xml` is empty in both. The whole game — logic and
artwork — is loaded at runtime from a third-party server:

| Scenario | Script |
|---|---|
| Fantasy RPG | `https://marvin.hs-bochum.de/~mneugebauer/alquiz-fantasy-bg-ver3-en.js` |
| Digital Mentoring (PA) | `https://marvin.hs-bochum.de/~mneugebauer/alquiz-qpool-instant-tutoring-en.js` |

The scripts in turn load 36 image files from the same server. This is what the port replaces: the
plugin serves everything itself, so nothing is fetched from anywhere else at runtime.

---

## Code

| File | Author | Licence | Source of the statement |
|---|---|---|---|
| `alquiz-qpool-instant-tutoring-en.js` | Malte Neugebauer, Hochschule Bochum | MIT | Licence header in the file |
| `alquiz-fantasy-bg-ver3-en.js` | Malte Neugebauer, Hochschule Bochum | MIT | Stated in the project's earlier review of the same author's `alquiz-fantasy-bg-ver3.js`; this English build carries no header of its own — **confirm** |

MIT is compatible with this plugin's GPL v3 licence. Code derived from either script keeps the
MIT copyright notice.

---

## Artwork

### Licence stated in the file — commercial, not redistributable

| File | Stated licence |
|---|---|
| `flag.svg` | Font Awesome **Pro** 6.1.2 — Commercial License, © 2022 Fonticons, Inc. |
| `house-solid.svg` | Font Awesome **Pro** 6.4.0 — Commercial License, © 2023 Fonticons, Inc. |
| `skull.svg` | Font Awesome **Pro** 6.1.2 — Commercial License, © 2022 Fonticons, Inc. |

A Font Awesome Pro licence belongs to its purchaser and does not permit redistribution in an
open-source project. **Replacement:** the same three symbols exist in Font Awesome **Free**
(`fa-flag`, `fa-house`, `fa-skull`), licensed CC BY 4.0 for icons, which Moodle core already ships.
The port uses those — no file is copied, and the attribution is Moodle's.

### Origin identifiable from the name, licence unresolved

| Files | Observation |
|---|---|
| `Elf_03__{ATTACK,IDLE,RUN}_spritesheet.png` | Naming scheme of craftpix.net character packs ("Fantasy Elf") |
| `Golem_01_1_*`, `Golem_02_1_*` spritesheets | Naming scheme of craftpix.net ("Golem") |
| `Troll_01_1_*` spritesheets | Naming scheme of craftpix.net ("Troll") |
| `bg-elven_land4.png`, `bg-forest1.png`, `bg-forest4.png` | 3840×2160, "Adobe ImageReady" in the PNG metadata; consistent with craftpix.net background packs |

None of these files carries author or licence metadata. craftpix.net licences — free and paid —
permit using the artwork **in** a product, but, as far as their published terms go, not
redistributing the source files themselves. A public repository is redistribution.
**Unresolved: confirm with the author which licence was acquired and what it permits.**

### Origin not identifiable

| Files | Observation |
|---|---|
| `fairy.svg`, `fairy-black.svg`, `fairy-black-paused.svg` | No metadata |
| `dm-avatar-{grin,happy,sad,think}.svg` | No metadata; possibly the author's own |
| `operators-white.svg` | No metadata; possibly the author's own |
| `oily-spiral-svgrepo-com.svg` | From SVG Repo; licence differs per icon there — **look up the individual icon** |
| `sign-post.png`, `wooden-sign-post-*.png` | "Created with GIMP" in the metadata — edited, source unknown |

**Unresolved.** Some of these may be the author's own work, which he could license directly.

### Missing on the source server

`Golem_01_1_ATTACK_spritesheet.png` and `Golem_01_1_WALK_spritesheet.png` are referenced by the
original script but return an empty response. The original game references artwork that does not
exist; the port does not reproduce those animations.

---

## Consequence for the plugin

Only material whose licence is established goes into this repository. Everything marked
*unresolved* is delivered as a **site-local design package**: imported through the Game Design
Studio, stored in Moodle's own file storage, served by Moodle. That satisfies the requirement that
nothing is loaded from outside at runtime, and it keeps artwork of unknown status out of a GPL
repository that anyone can copy.

Once a licence is confirmed, the corresponding row moves from *unresolved* to a stated licence, and
the asset may move into the repository if that licence allows it.
