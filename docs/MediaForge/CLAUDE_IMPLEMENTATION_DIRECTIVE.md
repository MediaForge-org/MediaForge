# Claude Implementation Directive

Diese Datei ergänzt die Master-Spezifikation für Claude Code.

## Vor jeder Implementierung

1. `CURRENT_PHASE.md` lesen und reale Phase respektieren.
2. `MediaForge_Master_Engineering.md` lesen.
3. `PRODUCT_DECISIONS_2026-08.md` lesen.
4. betroffene Modul-/Architekturdocs lesen.
5. keine spätere Phase vorziehen, nur weil sie interessanter ist.

## Harte Regeln

- Nie behaupten, ein späteres Ziel sei bereits implementiert.
- `CURRENT_PHASE.md` nur ändern, wenn Code + Tests den neuen Stand tatsächlich beweisen.
- Keine Fork-/ISO-/AI-Audio-Arbeit vor dem dafür vorgesehenen Gate.
- Keine sichtbare Weiterleitung auf Jellyfin/Stash/ABS als endgültige UX.
- PostgreSQL-Identität nicht durch Engine-IDs ersetzen.
- Adult Zero Leak serverseitig erzwingen.
- Disc-Auto-Mapping niemals aus Confidence ableiten.
- Originaldateien nicht ungefragt verändern/umbenennen.
- TypeScript bleibt verbindlich.
- UI-Referenzen unter `ui-ux/reference/` vor großen Frontend-Arbeiten ansehen.
- Jede neue Seite muss dieselbe Design-Sprache verwenden.
- Keine stillen Scope-Erweiterungen oder riskanten Fallback-Heuristiken.

## Abschluss eines Arbeitspakets

Claude soll liefern:
- geänderte Dateien;
- rationale Zusammenfassung;
- Tests/Gates und Ergebnis;
- bekannte Limits;
- keine Commits/Tags/Pushes ohne ausdrückliche Anweisung des Benutzers.

## Neue Architekturentscheidung (verbindlich)

Vor neuen großen UI-/Backend-Arbeiten zusätzlich lesen:

- `architecture/target-monorepo.md`
- `architecture/polyglot-runtime-and-contracts.md`
- `architecture/routing-and-public-urls.md`
- `DEVELOPMENT_PHASES_DETAILED.md`
- `ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`

Ziel ist React Router + API-first. Keine neue langfristige Funktion an Inertia koppeln. Bestehende Inertia-Funktionalität wird kontrolliert migriert; nichts blind löschen.

Alle neuen Feature-Screens 30–41 müssen vor dem jeweiligen Modul implementiert werden bzw. als visuelle Referenz geprüft werden.

P0 bedeutet: Datenmodell/Contract früh vorbereiten; es bedeutet **nicht**, dass aufwendige AI-Funktion sofort implementiert werden soll.

## Granular prompt execution system

For normal implementation work, use `docs/MediaForge/prompts/` instead of rereading the full specification on every turn. Start with `prompts/README_START_HERE.md`. Each numbered prompt defines its own minimal context budget. Do not preload unrelated documentation or images.

## Green-Commit- und Capability-Regeln 2026-08-16

- Keine neue Next.js-Schicht einführen; Web-Ziel ist React Router Framework Mode + MediaForge API v1.
- AI/3D niemals zu einer Core-Hard-Dependency machen.
- Keine großen Analysis-Artefakte als PostgreSQL-BLOB speichern.
- Keine Body-Model-/AI-Provider-Lizenz voraussetzen: Provider über Adapter/Model Registry, Lizenz muss vorgesehenen Use erlauben.
- Neue Adult-3D/Tattoo-Funktionen nur im entsperrten Adult-Kontext und mit vollständiger Zero-Leak-Behandlung.
- Standardentwicklung folgt `architecture/green-commit-workflow.md`.

## Product-surface, acquisition and localisation rules — 2026-08-17

- Do not implement MediaForge as a launcher for external Web UIs. MediaForge owns normal UX; upstream native UIs are advanced/admin fallbacks.
- During Track 02 import/pin Jellyfin, Stash and Audiobookshelf baselines without prematurely deleting their ability to build/run as references. Later tracks progressively adapt/cut over capabilities.
- Treat SABnzbd/qBittorrent/Prowlarr/Sonarr/Radarr/Whisparr as unmodified managed upstreams unless a later explicit ADR changes that rule.
- Acquisition logic must respect torrent seeding, staging/quarantine and deterministic naming/provenance. Do not hide destructive filesystem operations behind generic helpers.
- User-visible strings must use localisation keys. Launch locale completeness is de/en-GB/it/es/fr.
- Do not fabricate localised metadata. Prefer authoritative locale metadata; use the professional translation fallback only when needed and retain source/provenance.
- UI mockup artwork is illustrative; implementation must bind artwork to the canonical matched media identity.
