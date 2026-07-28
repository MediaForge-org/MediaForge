# Changelog

All notable changes to MediaForge are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[Semantic Versioning](https://semver.org/). Entries are generated from
[Conventional Commits](https://www.conventionalcommits.org/).

## [Unreleased]

Targeting the first tagged pre-release **`v0.2.0-alpha.1`** (V1 local core, alpha —
not production-ready). See [docs/MediaForge/V1_READINESS.md](docs/MediaForge/V1_READINESS.md).

### Fixed — double-submitted review task creation no longer crashes

- **A rapidly repeated snapshot, dry run, import or normalization could 500.** Every one of those
  actions reconciles a single review task through the same check-then-insert: look for an already
  open task for the (task_type, subject), reuse it if found, otherwise create one. Two requests
  close enough together — a double-submitted button, a retried request, two open tabs — could both
  pass the "no open task yet" check before either committed, so the second insert then hit the
  `review_tasks_no_duplicate_open` partial unique index and surfaced as an unhandled
  `UniqueConstraintViolationException`. `CreateReviewTask` now catches that violation and re-fetches
  the task the other request just won instead of letting it crash — the same "loser re-fetches the
  winner" pattern already used for import idempotency in `ExecuteMediaImportPlan`. Every caller
  (`CreateCatalogReviewTasks`, `CreateSyncReviewTasks`, `CreateNormalizationReviewTasks`,
  `CreateImportPlanReviewTasks`, `CreateMediaImportReviewTasks`) goes through this one action, so
  the fix covers all of them.
- Every action-triggering button in the UI already disabled itself and showed a loading state for
  the duration of its request (`Button`'s `loading`/`disabled` prop, used consistently across
  Connectors, Catalog, Imports, Sync and Review Center) — verified, not changed, by this fix.

### Fixed — V2 E.1: duplicate suspicion withheld real media

- **A re-scanned item was blocked as a duplicate of its own vanished predecessor.**
  The duplicate scan read every stored normalization row regardless of presence, while the plan
  itself only ever plans items that are still there. So when a server reissued its ids (a library
  rebuild), each fresh capture was paired with the dead row it replaced and held back from the
  import. On the development catalog this silently withheld **327 of 415 episodes** — whole seasons
  of Supernatural, and a series, reported as `skipped_duplicate`. Duplicate detection now runs over
  the same scope the plan does, so an item that is gone can no longer block the one that replaced it.
- **Duplicate identity now requires a shared parent, not just a shared title.** The old key was
  `title + kind + year`, which had no notion of which show an episode belonged to. The new
  `DuplicateIdentity` keys an **episode** on connector instance + parent container + season number +
  episode number and ignores the title entirely; a **season** on instance + show + season number;
  and a **series/movie/book/audiobook** on instance + title + a real release year. Consequences:
  two episodes of one season can never collide because both are called "Episode 1"; "Season 1" of
  Supernatural and of Chernobyl are different seasons; S01E01 of two shows are different episodes;
  and two same-named films without a year are reported as missing a year rather than blocked.
- **A duplicate no longer means nothing gets imported.** Previously every copy of a duplicated
  identity was withheld, so two captures of one episode produced *zero* episodes. Now one copy is
  elected (lowest ULID — deterministic, so repeated dry runs agree) and stays importable, while the
  extra copies are flagged `needs_review` + `duplicate_suspect` for a human. Nothing is merged: the
  extra copies keep their own rows and get no mapping, so the decision stays open.
- Duplicate identity is scoped to one connector instance. The same film on two servers is two
  external items; deciding they are one work is a matching question, not grounds for withholding
  an import.

### Added — V2 E: first internal import

- **The first package that writes real MediaForge media records.** `POST
  /imports/{plan}/execute-ready` turns a V2 D plan's **ready** lines into rows in the canonical
  `media_items` table — the foundation catalog that has stood empty since V1, whose own migration
  said the ingest pipeline would arrive in V2. V2 E is that pipeline.
- **New `/imports/runs/{run}` page**: one internal import in full — status, counts (imported ·
  linked existing · skipped · failed), a plain-language "why", and one bounded section per outcome
  (*Created media items*, *Linked existing*, *Skipped*, *Failed*) showing title, what happened,
  status, reasons and the source connector/library.
- **Real parent structure**: series → season → episode is built as an actual `parent_id` chain.
  Containers are imported before the things that hang under them (series → seasons → episodes →
  movies → audiobooks/books), so one pass always finds its parents.
- **Parents are resolved exactly, never guessed**: through the external parent id via an existing
  mapping (works across runs), or through the plan's own `target_parent_key` for something created
  moments earlier in the same run. If the two disagree the parent is *ambiguous*; if neither
  answers, or the candidate is the wrong kind, it is *missing*. Either way the line is skipped with
  a reason instead of being attached to a guess.
- **Idempotent by construction**: the new `media_external_mappings` table carries a unique
  `(connector_instance_id, external_id)`, so importing the same plan again **links** the existing
  record rather than creating a second one — and never overwrites it, so a human's edits survive.
- **Only ready lines are imported.** Needs-review, blocked, warning, skipped, duplicate-suspect,
  weak-metadata, unknown-kind, folder, playlist, podcast and music lines are all recorded as
  skipped with a reason. Every decision is made in one pure, testable gate.
- **New tables**: `media_import_executions`, `media_import_execution_items`,
  `media_external_mappings`, plus import provenance on `media_items` (`source`,
  `created_by_import_execution_id`, `metadata`, `season_number`, `episode_number`) and plausibility
  CHECKs on year/runtime/season/episode.
- **Vocabulary bridge**: the connector read-model's `series`/`book` map onto the foundation
  catalog's `show`/`ebook`, stated once in `ImportableMediaKind` so no other file has to know.
- One deduplicated **`media_import_execution` review task per plan** carries the reason codes,
  counts and example titles; a re-import supersedes it and a clean run raises none. Audit events
  `media_import.execution_completed` / `.execution_empty` / `.execution_failed`, all sanitized.
- **UI**: `/imports` gains an internal-media summary and a run list; `/imports/{plan}` gains the
  *Import ready items into MediaForge* button (only when ready lines exist, labelled **DB only**)
  and its run history; the dashboard gains an Internal Media panel; the catalog table gains an
  **Imported / Not imported / Needs review** column.
- **Still no file operations, still nothing leaves the machine.** The import copies, moves, deletes
  or renames no file, stores no file path (no path-like column exists in any imported table — a
  test reads the live schema to prove it), creates no `media_files` and no `media_editions`, and
  sends no request to Jellyfin or Audiobookshelf — no write, no scan, no library refresh. It
  accepts no match and merges no duplicate. A run is wrapped in one transaction, so a failure rolls
  back whole and records itself without a stack trace or a message.

### Added — V2 D: import plan / import dry run

- **New `/imports` page**: import plans overview with the latest dry run, its status
  (`ready` · `warnings` · `blocked` · `empty`), the counts that matter (planned, ready, needs
  review, blocked, duplicate suspects, unsupported, warnings) and the recent dry-run history.
- **New `/imports/{plan}` page**: one dry run in full — plan header, the planned target structure
  aggregated by kind and action, a plain-language "why", and one bounded section per outcome
  (*Ready to import later*, *Warnings*, *Needs review*, *Blocked*, *Skipped — unsupported*,
  *Duplicate suspects*), each row showing title, planned kind, planned action, status, confidence,
  reasons and the source connector/library.
- **POST-only `/imports/dry-run`** with a scope (`all` · `connector` · `library`), reachable from
  `/imports`, `/catalog`, `/catalog/{connector}`, `/catalog/{connector}/libraries/{library}` and
  `/catalog/matches`. An unknown connector, an unconfigured connector or a library that does not
  belong to it is a 404 — never a silently widened scope.
- **New plan tables** `media_import_plans` and `media_import_plan_items` (CHECK-constrained,
  indexed, rollback-safe). These are **plan** tables, not media tables: they hold a logical target
  identity (kind, title, year, season/episode plus a hashed stable key) and deliberately **no file
  path**, so there is nothing in a plan to move, copy, delete or rename.
- **Planning rules**: a clean movie/audiobook/book → `create_media` · *ready*; a series/season →
  `create_container`; an episode with a parent, a season number and an episode number →
  `attach_to_parent`; a missing year → *warning*; a missing season/episode number, an unknown kind
  or weak metadata → *needs review*; a missing title, a missing parent or an item that was never
  normalized → *blocked*; folders/playlists (and podcast/music, which the first internal import
  will not cover) → `skip_unsupported` · *skipped*, counted but never treated as errors.
- **Duplicate suspects are never automatically ready**: an item sharing a normalized identity with
  another captured item drops to `skip_duplicate` · *needs review* with a `duplicate_suspect`
  reason. Nothing is merged and no duplicate is ever resolved automatically.
- **Deterministic and bounded**: the pure `PlanCatalogItemImport` makes the same stored input
  always produce the same actions, statuses, reasons and `target_key`; items are streamed by ULID
  in chunks and one plan is capped at 5000 items, beyond which it reports itself `truncated`.
- One **deduplicated `media_import_plan` review task per dry-run scope** carries the reason codes,
  their counts and a few example titles; a re-run supersedes its predecessor and a clean plan
  raises none at all. Audit event `media_import_plan.created` is sanitized to counts, reason codes
  and scope.
- **Dashboard** gains an Import Plans panel (status + planned/ready/warning/blocked) and the
  sidebar gains an **Import Plans** entry.
- **Still not an import.** V2 D writes only plan rows, review tasks and audit entries. It creates
  no `media_items`, `media_editions` or `media_files`, performs and plans **no file operation**,
  makes no network call while planning or rendering, changes nothing on Jellyfin/Audiobookshelf and
  accepts no match. There is deliberately no execute / import / accept / merge action — and no
  route that could perform one. The first real internal import arrives in V2 E.

### Added — V2 C: catalog normalization and matching preview

- **Normalization**: every captured external item is interpreted into a consistent shape —
  cleaned title (whitespace collapsed, curly quotes/dashes/NBSP unified), derived sort title
  (leading article moved aside), classified kind, release year, season/episode numbers, parent
  title and runtime — stored as a read-only row per item in the new
  `connector_catalog_item_normalizations` table.
- **Deliberately conservative**: normalization only re-reads what the connector already reported.
  It never invents a year from a title, never regex-guesses season/episode numbers out of free text
  and never fills a gap with a plausible-looking value. An implausible year/runtime is flagged and
  dropped rather than "corrected"; a missing field stays missing and becomes a visible issue.
- **Quality verdict**: each item gets sanitized issue codes (`missing_title`, `unknown_kind`,
  `missing_season_number`, `missing_episode_number`, `missing_year`, `invalid_year`,
  `runtime_missing`, `invalid_runtime`, `short_title`, `weak_metadata`), a confidence (0–100
  derived from those issues) and a status (`clean` ≥90, `warning` ≥60, `needs_review` below, plus
  `unsupported` for structural containers like folders/playlists that are not media at all).
- **Runs automatically after a snapshot** (only on a successful read) and on demand via POST-only
  *Rebuild normalization* on `/catalog` and per library. Bounded: items are streamed in chunks and
  written with chunked bulk upserts.
- **New `/catalog/matches` page — matching PREVIEW only**: duplicate suspects (items sharing a
  normalized title+year+kind, with null-safe year pairing), episode grouping candidates (by series
  + season, reporting how many episodes lack a number), audiobook/book grouping candidates, and
  items with weak metadata. Every group carries a score and a plain-language reason.
  **There is deliberately no accept, import or merge action** — and no route that could perform one.
  The import plan arrives in V2 D.
- **Catalog UI**: normalization summary cards (each linking into the item list filtered to exactly
  that count), new filters (normalization status, issue code, duplicate-suspects-only), and a
  Quality column showing status, confidence and issue count. Items captured before V2 C render as
  "Not normalized" rather than breaking.
- Normalization problems raise **one deduplicated `catalog_normalization` review task per connector**
  carrying issue codes and counts in its evidence (five broken items produce one actionable task,
  not five); a clean rebuild dismisses it. Audit event `catalog.normalization_rebuilt` is sanitized
  to counts and issue codes.
- Still **100% read-only**: no media import, no `media_items`/`media_editions`/`media_files`, no
  file operations, no accepted matches, no changes on Jellyfin/Audiobookshelf, no network during
  normalization or render.

### Added — V2 B: catalog browsing, filters, pagination and paged snapshots

- **Browsable `/catalog`**: search by title, filter by connector / library / media kind /
  presence (present · missing · all), sort by title / last seen / year / kind in both directions,
  and paginate (24 per page). Filters live in the URL query string, so a filtered view is
  shareable and back-button friendly.
- **New read-only catalog pages**: `GET /catalog/{connector}` (connector summary, its libraries,
  latest runs and items) and `GET /catalog/{connector}/libraries/{library}` (library counts, last
  snapshot, truncation notice, POST-only snapshot button and the library's items). `{connector}` is
  constrained to registered keys, `{library}` to a ULID that must belong to that connector —
  anything else 404s. There is deliberately **no global `/libraries` route**.
- **Paginated snapshots** replace the rigid 500-item one-shot: a run now reads the remote one
  bounded page at a time (`PAGE_SIZE = 500`) up to a hard cap (`MAX_ITEMS_PER_SNAPSHOT = 5000`).
  Jellyfin pages via `StartIndex`/`Limit`, Audiobookshelf via its zero-based `page`/`limit`; tokens
  stay headers on every page. The loop is bounded by page count, item cap and the remote's reported
  total, so it can never run away, and duplicate ids across pages are collapsed.
- A truncated run (remote holds more than the cap, or a later page failed) is marked `truncated`,
  raises the `snapshot_truncated` warning review task, and reports `captured_count` / `remote_total` /
  `cap` in its summary.
- **An incomplete read never flags items missing** — vanished-item detection now runs only after a
  *complete* read, so a truncated or partially failed snapshot cannot mislabel the tail it never saw.
- Captured items are stored with a **chunked bulk upsert** instead of two queries per item, so a
  capped 5000-item run costs a handful of statements. `first_seen_at` stays insert-only.
- **Dev runtime after a laptop reboot** is documented (expected containers, how to start, verify and
  reset) with new `make dev-up` / `make dev-ps` / `make dev-doctor` helpers. No autostart service,
  Task Scheduler entry or registry change is installed.
- Readiness guardrail added: **every literal `href` in every `.tsx` must resolve to a registered GET
  route** (template literals included), so a link to a non-existent page fails the suite.
- Still **100% read-only**: no media import, no `media_items`/`media_editions`/`media_files`, no file
  operations, no automatic/background snapshots.

### Added — V2 A: read-only connector catalog snapshots

- **Read-only catalog snapshots**: explicitly triggered (POST-only), bounded snapshots of a
  connector library. External items are captured as a **connector read-model**, never as MediaForge
  media. **No media import, no `media_items`/`media_editions`/`media_files`, no file operations**,
  no changes on Jellyfin/Audiobookshelf, no remote scans, and no automatic/background snapshots.
- New tables `connector_catalog_snapshot_runs` (run history + sanitized summary) and
  `connector_catalog_items` (external items, unique per connector + external id).
- Jellyfin snapshots via the read-only `/Items?ParentId=` endpoint; Audiobookshelf via
  `/api/libraries/{id}/items`. Tokens are sent as headers only, never in a query string; no raw API
  payloads are stored. A `supportsCatalogSnapshot()` capability models providers that cannot
  snapshot yet — handled explicitly without any network call.
- **Bounded to 500 items per run.** A larger library marks the run `truncated` and raises a
  `snapshot_truncated` warning review task.
- Vanished items are flagged (`is_present=false` + `missing_since`), never deleted; a *failed*
  snapshot never flags or wipes previously captured items.
- New `/catalog` page (summary cards, connector cards, latest runs, latest external items, empty
  state, safety note) plus catalog blocks on the dashboard, connectors overview and connector
  detail (with per-library "Create read-only snapshot" buttons), and a real sidebar link.
- Snapshot problems raise deduplicated `connector_catalog` review tasks; a clean snapshot
  self-heals the queue. Audit event `connector.catalog_snapshot_completed` is sanitized.

### Added — V1 local core (alpha)

- **V1 A — Auth**: local session authentication (login, register, POST-only logout),
  protected routes, roles/policies foundation.
- **V1 B — App shell**: authenticated layout, dashboard, settings foundation, Windows/Docker
  production-build runtime stability.
- **V1 C — Connectors**: Jellyfin & Audiobookshelf connector configuration with encrypted
  secret store and on-demand `testConnection()` diagnostics.
- **V1 D — Library discovery**: discover libraries a connector exposes; select libraries for a
  later sync (library-level metadata only, no media items).
- **V1 E — Premium UI/UX**: design system, switchable design presets, light/dark themes, and a
  large-screen layout.
- **V1 F — Sync Foundation**: dry-run sync runs with per-library plan, run history, and a `/sync`
  page. Dry run only — no import, no file operations.
- **V1 G — Review Center**: central `/review` page aggregating review tasks and connector/sync
  health, with dismiss/reopen for connector-sync tasks and a dashboard summary.
- **V1 H — Final hardening + readiness**: refreshed README and phase/runtime docs, verified
  `.env.example`, navigation/security review, readiness documentation, and green quality gates.

### Foundation (V0)

- Laravel 12 / React + Inertia + TypeScript + Tailwind v4 application skeleton.
- Modular-monolith structure (`app/{Core,Modules,Connectors,Http}`) with
  architecture boundary tests.
- Docker: multi-stage production image, dev + production Compose stacks, Makefile.
- 12-factor environment configuration for the future official Docker image.

### Security

- Connector API tokens are stored only in the encrypted DB secret store; they are never sent to
  the frontend, never placed in Inertia props/DOM, and are masked in audit logs and review evidence.
- Audit logging and review-task evidence are sanitized; no raw remote API responses are stored.
- All state-changing routes are POST-only (including logout); CSRF stays enabled for real requests.
- No network calls occur while rendering pages — health/sync data is read from stored state only.
