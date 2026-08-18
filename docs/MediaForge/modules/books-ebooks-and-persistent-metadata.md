# Books, ebooks and persistent metadata

**Status:** binding target architecture

## Scope

MediaForge supports textual books/ebooks as a first-class domain alongside audiobooks. Useful metadata already learned from Audiobookshelf, embedded files, sidecars or providers must survive renames, moves, library-root changes, rescans and engine-local id changes.

PostgreSQL is canonical. Paths, filenames and upstream ids are observations/mappings, never book identity.

## Canonical literary model

```text
LiteraryWork
├── BookEdition
│   ├── BookFile (EPUB)
│   ├── BookFile (PDF)
│   └── BookFile (other supported readable format)
└── AudiobookEdition
    ├── Chapter model
    └── AudioFile(s)
```

Translations, abridgements, revisions, annotated editions and materially different publisher editions are not collapsed merely because the title matches.

A BookEdition and an AudiobookEdition may belong to the same LiteraryWork while keeping their metadata and progress domains separate.

## Metadata

The canonical model must support provenance-bearing values for at least:

- title, subtitle and sort title;
- authors and contributors;
- editors, translators and illustrators;
- narrators for audiobook editions;
- series and volume/order;
- language and original language/title where known;
- publisher/imprint;
- publication/release date and edition statement;
- description/synopsis;
- subjects/tags/genres;
- cover/artwork references;
- page count where meaningful;
- ISBN-10, ISBN-13 and provider-specific identifiers;
- acquisition/import provenance;
- manual values and locks.

Provider/engine ids are mappings, not MediaForge primary ids.

## Rename/move invariants

Filesystem path and filename are mutable locations.

After metadata has been captured, any of these must preserve the same canonical Work/Edition and its source facts:

- filename rename;
- folder move;
- library-root move;
- MediaForge-controlled rename/move;
- Audiobookshelf rescan;
- changed ABS-local item id;
- temporary disappearance from an upstream source.

A move updates FileLocation/path history. It does not erase metadata, reading/listening state, bookmarks, annotations or source history.

## Stable file/content identity

Reconciliation uses the strongest evidence available:

1. MediaForge-controlled identity during internal move/rename;
2. exact content hash;
3. media/content fingerprint that survives irrelevant tag/container changes where practical;
4. stable edition/provider identifiers;
5. strong metadata + technical evidence;
6. ambiguous candidates -> Review, never silent merge.

For large audio, quick fingerprints may precede a full cryptographic hash. For ebooks, exact hashes are strong for pure moves/renames and structural/content fingerprints may supplement them when embedded metadata changes.

`File` and `FileLocation` are separate concepts.

## Audiobookshelf metadata retention

When MediaForge successfully reads Audiobookshelf metadata, useful values are persisted as MediaForge source facts/snapshots in PostgreSQL.

A later ABS response that omits a value, changes a path, changes an engine-local id or no longer exposes the item must not delete those retained facts.

Refresh may append newer facts and change a canonical choice according to policy, but:

- provenance/history remains;
- manual locks/curated values are not silently overwritten;
- source disappearance changes source availability, not canonical book existence.

## First-class Books product area

Books must have a real product surface, not an audiobook attachment:

- Books libraries;
- browse/search/filter/sort;
- authors/contributors;
- series;
- editions and multiple digital formats;
- language/publisher/identifiers;
- cover management;
- metadata provenance/review;
- reading progress and cross-device sync;
- bookmarks;
- highlights;
- notes/annotations;
- table of contents;
- in-book search where supported;
- import/move/rename/storage policies.

Catalog-only editions may exist without a local readable file, but local reading requires a file.

## Formats and reader

First-class reader targets:

- EPUB;
- PDF.

The ingestion/storage model is capability-driven and extensible to common readable DRM-free formats such as MOBI, AZW3, FB2, TXT and HTML where a parser/reader/conversion capability exists.

MediaForge does not implement DRM circumvention. Unsupported/protected formats are reported honestly.

### EPUB reader target

- reflowable layout;
- paginated and scrolling modes;
- TOC;
- font/size/line-spacing controls;
- reading themes;
- search;
- bookmarks;
- highlights/annotations;
- stable reading locators;
- keyboard/touch/mobile/desktop support.

Use a stable locator abstraction (for example EPUB CFI/text anchors), not only transient pixels.

### PDF reader target

- page navigation;
- TOC/thumbnails where available;
- text search where extractable;
- zoom/fit;
- bookmarks;
- notes/highlights where supported;
- page + stable relative locator for progress.

## Reading state

Text reading progress is distinct from audiobook playback position. Both may relate to one LiteraryWork, but MediaForge does not pretend that a text locator and an audio timestamp are inherently equivalent.

Per-user reading state may include:

- current locator;
- completion/progress;
- last-read timestamp;
- bookmarks;
- highlights;
- annotations/notes.

MediaForge owns this state, not the ABS database.

## Metadata extraction

Sources may include:

- EPUB package metadata;
- PDF/XMP;
- OPF/sidecars;
- explicit filename/folder parsers;
- Audiobookshelf-derived metadata;
- provider plugins;
- manual edits.

Extracted values become source facts with provenance. Portable sidecars may be exported, but PostgreSQL remains canonical.

## Naming/storage

Book and audiobook naming follows configurable MediaForge templates. Original filenames remain provenance. Renaming/moving through MediaForge retains Work/Edition/File identity.

## Required verification

Later implementation must cover:

- rename with unchanged content;
- folder/library-root move;
- ABS rescan with changed path/id;
- source disappearance while retained metadata remains;
- content hash/fingerprint reconciliation;
- ambiguous relocation -> Review;
- EPUB/PDF import and reading;
- multiple formats for one edition;
- book series/order and contributors;
- reading progress/bookmarks/highlights/notes;
- source-fact provenance;
- migration/backfill of pre-existing book/audiobook data.
