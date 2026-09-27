# MediaForge milestone test strategy

Testing is part of every execution unit, not a later cleanup phase.

## Minimum for code-bearing units

1. focused tests for changed behavior;
2. meaningful negative/failure-path tests when failure is possible;
3. static/type/format checks for touched languages;
4. integration tests when crossing DB/API/process/service boundaries;
5. broader regression gates for shared/startup/routing/contracts/schema changes.

Documentation-only units still run structural validators and `git diff --check`.

## Mandatory depth by risk

### PostgreSQL/migrations
Fresh migration, upgrade from previous real schema, constraints/FKs, duplicate/idempotency/concurrency, representative backfill, rollback or documented forward-fix, hot-query/index checks.

### API/security
Happy path, unauthenticated, unauthorized, invalid input, not-found/wrong ownership, conflict/idempotency, no secret/path/stack leakage, abuse/rate boundaries where relevant.

### UI/client
Loading, empty, error, disabled/unavailable, responsive, keyboard/focus, accessibility, critical browser E2E, TV/remote navigation where relevant.

### External adapters
Supported + unsupported versions, auth failure, timeout/offline, malformed/partial response, pagination, duplicates/reordering, capability absence, idempotent resync, last-known-good outage behavior.

### Files/imports
Temp fixtures, rename/move, retry, duplicate execution, partial failure, ambiguous relocation -> Review, original immutability, hardlink/cross-filesystem behavior where relevant.

### Jobs/realtime
Duplicate delivery, retry/backoff, crash/restart, persistent checkpoints, cancellation, out-of-order/reconnect, event authorization/privacy.

### Playback
Direct play/remux/transcode/degraded fallback, unsupported codecs/capabilities, tracks/subtitles, resume/progress, session failure/reconnect, client differences, large bytes bypass Laravel.

### Adult/private
Locked + unlocked direct-boundary tests for routes, search, artwork/preload, history/activity, notifications, realtime payloads, cache, logs and existence probing.

### AI/native processing
Provider/model absence, model/version/provenance, resource bounds, deterministic contracts, crash recovery, private artifact isolation, source immutability.

### Release
Clean install, populated upgrade, failure recovery, rollback/forward-fix, backup restore, service-version compatibility, artifact integrity and full-system E2E.

## Milestone gates

The final unit of every milestone is an integrated gate. It may skip irrelevant repository-wide suites only with an explicit reason.
