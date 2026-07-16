# Changelog

All notable changes to MediaForge are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[Semantic Versioning](https://semver.org/). Entries are generated from
[Conventional Commits](https://www.conventionalcommits.org/).

## [Unreleased]

Targeting the first tagged pre-release **`v0.2.0-alpha.1`** (V1 local core, alpha —
not production-ready). See [docs/MediaForge/V1_READINESS.md](docs/MediaForge/V1_READINESS.md).

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
