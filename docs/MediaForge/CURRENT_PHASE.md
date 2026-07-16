# MediaForge Current Phase

## Current status

**V1 (local core, alpha) is complete. V2 has begun with Package A — read-only connector
catalog snapshots.**

V1 was delivered as eight focused packages (A–H). The application runs locally from a stable
production build, and all local gates (Pint, PHPStan max, Pest, TypeScript type-check, Vite build)
are green.

MediaForge remains **local alpha software — not production-ready**. It performs **no media import**,
**no file operations**, and **no automatic sync or background snapshots**.

## Confirmed stack

- Backend: Laravel 12, PHP 8.4
- Frontend: React 19 + Inertia.js + TypeScript, Tailwind CSS v4
- Database: PostgreSQL 17 (pg_trgm, btree_gist, pgvector)
- Cache/Queue: Redis 7
- Dev environment: Docker / Docker Compose
- Tests: Pest (incl. architecture boundary tests)
- Static analysis: PHPStan (max level)
- Formatting: Pint

Vue is not the frontend stack and must not be reintroduced.

## V1 packages — completed

- **V1 A — Auth**: login, register, logout (POST-only), protected routes, roles/policies foundation.
- **V1 B — App shell**: authenticated layout, dashboard, settings foundation, runtime stability.
- **V1 C — Connectors**: connector configuration + `testConnection()` for Jellyfin & Audiobookshelf.
- **V1 D — Library discovery**: discover libraries a server exposes; select libraries for later sync.
- **V1 E — Premium UI/UX**: design system, design presets, large-screen (2560×1600) layout.
- **V1 F — Sync Foundation**: dry-run sync runs, per-library plan + run history, `/sync` page.
- **V1 G — Review Center**: central review tasks + health foundation, dismiss/reopen, `/review` page.
- **V1 H — Final hardening + readiness**: README/docs refresh, `.env.example`, runtime docs,
  navigation/security review, readiness documentation, all gates green.

See [V1_READINESS.md](V1_READINESS.md) for the readiness checklist and release recommendation.

## V2 packages

- **V2 A — Read-only connector catalog snapshots** *(done)*: explicitly triggered, bounded,
  read-only snapshots of a connector library. External items are captured as a **connector
  read-model** (`connector_catalog_items`) with snapshot run history
  (`connector_catalog_snapshot_runs`), surfaced on a new `/catalog` page plus the dashboard,
  connector overview and connector detail. Snapshot problems raise deduplicated
  `connector_catalog` review tasks and write sanitized audit entries.
- **V2 B — Catalog browsing + paginated snapshots** *(current)*: makes the catalog usable —
  search/filter/sort/pagination over captured items, per-connector and per-library catalog pages,
  and multi-page snapshot reads that replace the rigid 500-item one-shot. Still 100% read-only.

### V2 B — catalog browsing

- **`/catalog`** — overview: summary cards, connector cards, latest runs, plus a **browsable,
  paginated item list** with search, connector/library/kind/presence filters and sorting.
- **`/catalog/{connector}`** — one connector: scoped summary, its libraries (linking to the library
  pages), its latest runs and its items.
- **`/catalog/{connector}/libraries/{library}`** — one library: scoped counts, last snapshot,
  truncation notice, a POST-only *Create read-only snapshot* button and the library's items.
  `{connector}` is constrained to registered registry keys and `{library}` to a ULID that must
  belong to that connector — anything else is a 404. There is **no global `/libraries` route**.
- **Filters live in the URL** (`q`, `connector`, `library`, `kind`, `status`, `sort`, `direction`,
  `page`) so a filtered view is shareable and back-button friendly.
- **Allowlisted, never raw.** Sort (`title`/`last_seen_at`/`year`/`media_kind`), direction
  (`asc`/`desc`), presence (`present`/`missing`/`all`) and kind are validated against allowlists in
  `CatalogItemQuery`; an invalid value falls back to the default instead of reaching SQL. Search is
  a bound `ILIKE` parameter with its wildcards escaped. Page size is fixed (24).

### V2 B — snapshot pagination

- A snapshot now reads the remote **one bounded page at a time** (`PAGE_SIZE = 500`) and pages until
  the remote is exhausted or the hard cap (`MAX_ITEMS_PER_SNAPSHOT = 5000`) is reached.
- Jellyfin pages via `StartIndex`/`Limit`; Audiobookshelf via its zero-based `page`/`limit`. Tokens
  stay headers on every page.
- The loop is bounded three ways — a hard page count derived from the cap, the item cap, and the
  remote's own reported total — so it can never run away. Duplicate ids across pages are collapsed.
- **Truncation** (`remote_total > captured`, or hitting the cap when the remote hides its total, or
  a later page failing) marks the run `truncated`, raises a `snapshot_truncated` warning review task,
  and the summary reports `captured_count` / `remote_total` / `cap`.
- **An incomplete read never flags items missing.** Vanished-item detection only runs after a
  *complete* read, so a truncated/partial snapshot can't mislabel the tail it never looked at.
- Captured items are written with a **chunked bulk upsert** (conflict target
  `(connector_instance_id, external_id)`), so a capped run costs a handful of statements rather than
  two queries per item. `first_seen_at` is insert-only, so a re-captured item keeps its identity.

### V2 A/B boundaries and limits

- **Read-only.** A snapshot READS external items and stores them for display. It is **not** an
  import: it creates no `media_items`, `media_editions` or `media_files`, performs **no file
  operations**, changes nothing on Jellyfin/Audiobookshelf, and starts no remote scans.
- **Explicit only.** A snapshot runs only on an explicit `POST` from a connector detail page or a
  catalog library page. There is no automatic, scheduled or background snapshot.
- **Never rendered from the network.** All catalog pages read stored state only.
- **Vanished items are flagged, never deleted** (`is_present=false` + `missing_since`); a *failed*
  snapshot never flags or wipes previously captured items.

### Known V2 B limitations

- **Capped at 5000 items per snapshot run.** A larger library is captured up to the cap and marked
  truncated; there is no resumable/continued snapshot job yet.
- **Snapshots are synchronous.** A capped 5000-item run does up to 10 sequential remote reads inside
  the request; it is not queued yet.
- **Audiobookshelf pagination assumes** the documented zero-based `page`/`limit` contract of
  `/api/libraries/{id}/items`. If a server ignores `page`, the duplicate-id collapsing keeps the data
  correct and the run is simply reported as truncated.
- Only `title`/`kind`/`year`/`index`/`runtime`-level fields are captured — no artwork, no
  descriptions, no file paths, no raw API payloads.
- Item search covers `title`/`original_title`/`sort_title` only; there is no full-text/fuzzy search.

## What V1/V2 A/B deliberately does NOT include

Real media imports · media items / editions / files · file operations (copy/move/delete/rename) ·
real or automatic sync (dry run only) · automatic/background snapshots · metadata-merge or
enrichment engine · download engine · disc/ISO/AV1 pipeline · fork integration · admin UI · profile
management · role management UI · password reset / email verification · adult engine · AI engine ·
plugin engine · mobile/desktop app.

## Known issues

- **Local alpha only** — not hardened for public/internet exposure.
- **Docker containers after a laptop reboot** — Docker Desktop does not reliably restart every
  container, so an empty browser after a reboot usually means the stack is not running. Start it with
  `make dev-up`, verify with `make dev-ps`, and diagnose with `make dev-doctor`. See
  [dev-runtime.md](dev-runtime.md#after-a-laptop-reboot) for the expected container list. MediaForge
  installs no autostart service or system change for this on purpose.
- **Windows/Docker runtime** — use the production-build mode (default). The Vite HMR server can stall
  on Windows bind mounts; `make runtime-reset` recovers. Because the dev/production PHP overlay keeps
  OPcache timestamp validation disabled, recreate the `app` container after changing PHP files
  (`docker compose -f deploy/dev/docker-compose.yml up -d --force-recreate --no-deps app`) so the
  running web server picks up the new code.
- **Method-mismatch requests** (e.g. issuing `GET` to a POST-only route) can hang on the local dev
  web server instead of returning `405`. This is a dev-server quirk, not an app regression; the test
  suite confirms state-changing routes are POST-only.

## Recommended next step

1. Keep all local gates green, commit, push `main`, confirm GitHub CI green.
2. **V2 C — queued / resumable snapshots**: move the paged read off the request into a queued job so
   a library larger than the 5000-item cap can be captured across runs, with progress reporting.
   Still read-only.
3. Later V2: read-only content strips (recently added / continue watching) on the dashboard —
   still no writes to media servers, no imports, no file operations.

## Rules for AI coding agents

- Do not read the full multi-MB master prompt unless explicitly needed; this file plus
  [V1_READINESS.md](V1_READINESS.md) is the source of truth for the active phase.
- Work in small packages. Do not commit, push, or create releases/tags automatically.
- After each package, report changed files, validation results, open issues, and a recommended
  (not executed) commit command.
