# Adult Engine Target

## Ziel

Langfristig wird der Adult-Media-Core als **direkter Stash-derived Fork** in das MediaForge-Ecosystem integriert. Das ist keine bloße Theme- oder Connector-Lösung.

## Warum Stash als Basis

Wiederverwendet werden sollen insbesondere:
- Go Media-Core;
- File/Library Scan;
- FFmpeg-Integration;
- Fingerprinting;
- Thumbnails/Previews/Sprites;
- Streaming/Transcoding-Grundlagen;
- Scene/Performer/Studio-Domäne;
- Scraper-/Plugin-Konzepte.

## Was MediaForge darüber baut

- komplett neue MediaForge React/TypeScript-UI;
- kanonische PostgreSQL-Identität;
- library-driven Performer Discovery;
- Source Provenance und Source History;
- StashDB + TPDB + FansDB + Studio/Creator/Tube/Historical Adapters;
- Coverage;
- Quality/Versions;
- Zero-Leak Private Mode;
- MediaForge Engine Contracts;
- gemeinsame Auth/Lifecycle/Health.

## Fork-Timing

Der direkte Fork bleibt in der großen Fork-/Ecosystem-Phase spät, damit MediaForge-Core und Engine Contracts zuerst stabil werden. Ein separater experimenteller Adult-Fork darf vorher entstehen, aber:
- keine MediaForge-Core-IDs duplizieren;
- keine endgültigen Engine-Verträge erfinden;
- Upstream-Stash-Historie erhalten;
- Lizenz-/NOTICE-Pflichten erhalten;
- keine Abhängigkeit vom originalen Stash-Frontend erzeugen.

## Datenbanken

PostgreSQL bleibt MediaForge Source of Truth. Falls der Stash-Fork während der Migration noch SQLite nutzt, ist das **interne Übergangspersistenz**. Der Core greift niemals direkt darauf zu.

Langfristig kann der Adult-Fork selbst auf PostgreSQL migriert werden, aber diese Migration ist getrennt von der ersten Fork-Integration zu planen.

## Lizenz

Der Fork muss AGPL-kompatibel gepflegt werden. MediaForge ist bereits AGPL-3.0-or-later; genaue Copyright-/NOTICE- und Upstream-Hinweise müssen beim Fork erhalten bleiben.

## Monorepo Placement and Upstream Sync

Zielpfad:

```text
engines/adult/
```

Der Stash-derived Code liegt im selben MediaForge-GitHub-Repository. Das Verzeichnis erhält mindestens:

- `UPSTREAM.md` mit Stash-Remote/Commit/Importhistorie;
- erhaltene Lizenz-/Copyright-Dateien;
- dokumentierte Upstream-Sync-Kommandos;
- MediaForge-spezifische Integrationsbereiche, soweit ohne unnötige Fork-Divergenz möglich.

### Kein Frontend-Duplikat

Der Stash-derived Fork soll nicht langfristig ein zweites normales React-Frontend parallel zu MediaForge pflegen. Die MediaForge Web-App ist die Produktoberfläche. Eine Upstream-/Debug-UI darf nur für Entwicklung erhalten bleiben, wenn sie Upstream-Merges erleichtert.

### API/Events

Adult Engine spricht `packages/contracts/engines`. Fachobjekte werden auf MediaForge-ULIDs gemappt. Full Analysis kann Rust/Python Services nutzen, ohne Stash-Core-Funktionen unnötig neu zu implementieren.
