# Hivelog — Project Management Vault

A lightweight, Git-tracked [Obsidian](https://obsidian.md) vault for managing
work on the **Hivelog** Drupal 11 module. Everything here is plain Markdown, so
it version-controls cleanly alongside the code it documents.

## How to open

Open either `docs/` **or** `docs/project-management/` as an Obsidian vault — the
dashboard queries are tag-based (`#hivelog/...`), so they resolve no matter
which of the two you pick as the vault root:

> Obsidian → *Open folder as vault* → select `docs` (recommended)

Opening `docs/` (rather than the repo root) keeps the vault scoped to
documentation and stops Obsidian from indexing `src/`, `tests/`, `vendor/`,
etc. Obsidian writes its own state into a `.obsidian/` folder at whichever
level you open; the volatile parts are git-ignored (see below).

## Structure

The vault lives at the root of the `hivelog` repository:

```
docs/                          ← vault root (recommended)
├── .gitignore                 ← ignores volatile Obsidian state (any depth)
├── .obsidian/                 ← Obsidian config for this vault
└── project-management/
    ├── README.md              ← you are here
    ├── index.md               ← dashboard / map-of-content (open this first)
    ├── templates/             ← copy these when creating new notes
    ├── projects/              ← multi-task initiatives
    ├── tasks/                 ← atomic units of work (NNNN-slug.md)
    ├── decisions/             ← Architecture Decision Records (ADRs)
    ├── releases/              ← per-version changelog + checklist notes
    └── notes/                 ← daily / weekly review notes
```

## Conventions

- **IDs**: tasks and decisions are zero-padded (`0001`, `0002`, …) so they sort
  and link predictably. Never reuse or renumber an ID once committed.
- **Frontmatter**: every note carries YAML frontmatter (`status`, `created`,
  etc.) so [Dataview](https://github.com/blacksmithgu/obsidian-dataview) can
  build the dashboard automatically.
- **Tags**: every note declares a `hivelog/<type>` tag in frontmatter
  (`hivelog/task`, `hivelog/project`, `hivelog/decision`, `hivelog/release`,
  `hivelog/notes`, `hivelog/review`).
  The dashboard queries with `FROM #hivelog/task` etc. — tag sources are
  independent of the vault root, so the queries work whether you open `docs/`
  or `docs/project-management/`.
- **Wikilinks**: cross-reference with real note links like
  `[[0001-queen-observation-csv-export]]` and
  `[[queen-observation-enhancements]]`. Avoid leaving placeholder wikilinks in
  templates, because Obsidian treats them as unresolved notes and they clutter
  graph/backlink views.
- **Status vocabulary**: `backlog` → `todo` → `in-progress` → `review` →
  `done` (or `blocked` / `dropped`). Keep it to these values so queries stay
  reliable.

### Frontmatter formatting

Keep frontmatter mechanically consistent so Obsidian, Dataview, and simple
repo-wide searches behave predictably.

- Prefer **inline YAML arrays** for short lists:
  - `tags: [hivelog/task]`
  - `tags: [hivelog/notes, daily, journal]`
  - `depends-on: ["[[0004-responsive-foundation-and-breakpoints]]"]`
- Use **exact field values** in frontmatter:
  - `release: 1.4.0` (not `v1.4.0`)
  - `project: "[[queen-observation-enhancements]]"`
  - `status:` only from the vocabulary above
- Prefer **blank values over placeholders** when something is not known yet:
  - `target:`
  - `release:`
  - `blocked-by:`
  - do **not** leave fake links like `[[project-note]]` or `[[NNNN-...]]`
- When editing an existing note, preserve the current field order unless there
  is a strong reason to change it.

## Recommended Obsidian plugins

- **Dataview** — powers the dashboard tables in `index.md`.
- **Tasks** — query `- [ ]` checkboxes across notes.
- **Templater** (or core *Templates*) — instantiate `templates/*.md`.
- **Git** — auto-commit the vault on an interval if you like.

## Git workflow

The vault lives in the same repository as the module, so notes and code travel
together.

1. **Branch per task**, naming it after the task ID:
   ```
   git checkout -b feature/0001-queen-observation-csv-export
   ```
2. **Reference notes in commits** so history is self-documenting:
   ```
   git commit -m "feat(queen): CSV export for observations

   Implements docs/project-management/tasks/0001-queen-observation-csv-export.md
   Decision: 0001-geofield-over-geolocation (N/A) — see task for rationale."
   ```
3. **Commit the note with the code it describes.** Updating a task's `status`
   to `done` in the same commit that lands the feature keeps the vault honest.
4. **Use the repository's actual tag format.** HiveLog tags releases as
   `1.2.0`, `1.3.0`, etc. (no `v` prefix), and the vault mirrors that in
   `releases/`.
5. **Releases**: when tagging a version, finalize the matching
   `releases/X.Y.Z.md` note in the release commit.

## .gitignore

`docs/.gitignore` ignores Obsidian's volatile per-machine state
(`**/.obsidian/workspace.json`, `workspace-mobile.json`, caches, `.trash/`) at
any depth, so it covers a `.obsidian/` folder whether you open `docs/` or
`docs/project-management/`. Shareable config (`app.json`, `appearance.json`,
hotkeys, community-plugin settings) is intentionally tracked so the vault setup
travels with the repo.
