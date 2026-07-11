# Deployment: Docker Compose, Volumes, Upgrades

Dieses Deployment beschreibt eine lokale Enhancement Suite neben Jellyfin und Audiobookshelf. Jellyfin und Audiobookshelf bleiben eigenständige, updatefähige Dienste; MediaForge wird daneben betrieben und spricht ihre lokalen APIs an. Ein Reverse Proxy ist optional und lokal betreibbar. Es gibt keine Cloudpflicht und keinen externen Pflichtdienst für Kernfunktionen.

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](overview.md) (Container-Topologie), [database/migrations.md](../database/migrations.md) (Upgrade-Ablauf). Normativ für die ausgelieferten Compose-Dateien und die Betriebsdokumentation.

> **Implementierungsstatus V0:** Aktuell ausgeliefert sind der Laravel-App-Container, Queue-/Scheduler-Rollen, PostgreSQL, Redis und der Dev-Stack. `media-tools`, `ai-worker`, geführtes Web-Setup, Release-Images und die später beschriebenen Betriebsprofile sind Roadmap, nicht V0-Implementierung.

## Motivation

Die Zielgruppe betreibt MediaForge neben Jellyfin und Audiobookshelf auf einem Heimserver — Docker-Compose-Kompetenz ist vorhanden, Kubernetes-Kompetenz nicht vorauszusetzen ([ADR-0001](../adr/0001-technology-stack.md)). Das langfristige Deployment-Ziel sind Erstinstallation unter 15 Minuten, einfache abgesicherte Upgrades und Schutz vor Datenverlust. V0 belegt davon ausschließlich das reproduzierbare Entwickler-Setup, gültige Compose-Topologien, getrennte Persistenz und sichere Port-/Environment-Defaults; Web-Setup und Release-Upgrades folgen erst in den dafür freigegebenen Phasen.

## Compose-Topologie

Die Dienste aus [architecture/overview.md](overview.md), als ausgelieferte `docker-compose.yml` mit Profilen:

```yaml
# Auszug — normative Struktur, nicht vollständige Datei
services:
  app:
    image: mediaforge/app:${MEDIAFORGE_VERSION:-latest}
    depends_on: {postgres: {condition: service_healthy}, redis: {condition: service_healthy}}
    volumes:
      - ./.env:/var/www/html/.env:ro
      - media_movies:/media/movies:ro          # Medien IMMER :ro (ADR-0005)
      - media_audiobooks:/media/audiobooks:ro
      - artifacts:/artifacts
      - inbox:/inbox                           # einziger rw-Medienbereich (optional)
    ports: ["127.0.0.1:${MEDIAFORGE_PORT:-8100}:8080"] # nur localhost
  worker-default: {image: mediaforge/app, command: "php artisan queue:work --queue=default,connector", volumes: …}
  worker-scan:    {command: "… --queue=scan,assemble"}
  worker-analyze: {command: "… --queue=analyze"}
  scheduler:      {command: "php artisan schedule:work"}
  horizon:        {command: "php artisan horizon"}
  postgres:
    image: pgvector/pgvector:pg17
    volumes: [pgdata:/var/lib/postgresql/data]
    healthcheck: {test: ["CMD-SHELL", "pg_isready -U mediaforge"]}
  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes: [redisdata:/data]
  media-tools:
    image: mediaforge/media-tools:${MEDIAFORGE_VERSION:-latest}
    volumes: [media_movies:/media/movies:ro, media_audiobooks:/media/audiobooks:ro, artifacts:/artifacts]
    networks: [internal]                       # kein Egress: internes Netz ohne Gateway-Route
  ai-worker:
    profiles: ["ai"]                           # optional: nur mit --profile ai
    image: mediaforge/ai-worker:${MEDIAFORGE_VERSION:-latest}
    volumes: [media_audiobooks:/media/audiobooks:ro, artifacts:/artifacts, models:/models:ro]
    deploy: {resources: {reservations: {devices: [{driver: nvidia, count: 1, capabilities: [gpu]}]}}}
```

Verbindliche Eigenschaften: **Direkte Medien-Mounts sind optional und standardmäßig `:ro`**; `app` bindet nur an localhost, und TLS-Terminierung bzw. Exposition sind Sache des Betreibers. `media-tools`, `ai-worker`, No-Egress-Netze, Versions-Handshake und Reverse-Proxy-Beispiele sind spätere Roadmap-Bausteine und derzeit nicht als funktionsfähig ausgeliefert.

FrankenPHP vs. FPM+Nginx (offener Punkt aus [architecture/overview.md](overview.md)) — Entscheidung: **FPM+Nginx im selben `app`-Container** für Version 1. Begründung: Octane/FrankenPHP-Langlebigkeit bringt Latenzgewinne, die ein Verwaltungs-UI nicht braucht, und kauft Memory-Leak-Wachsamkeit über alle Module ein; die Worker sind ohnehin eigene Prozesse. Revidierbar, wenn Latenz-Messungen es begründen; als Nachtrag im Architektur-Kapitel vermerkt.

## Volumes und Verzeichnis-Layout

| Volume | Inhalt | Sicherungsklasse |
|---|---|---|
| `pgdata` | PostgreSQL | **kritisch** — primärer lokaler Persistenzspeicher |
| `artifacts` | erzeugte Artefakte | wichtig — rekonstruierbar, aber teuer (GPU-Stunden) |
| `redisdata` | Queues/Cache | verzichtbar — Verlust kostet nur Effizienz (Fundament-Regel) |
| `models` | AI-Gewichte | verzichtbar — neu beschaffbar per Registry-Doku |
| `media_*` | Bibliotheken | extern verwaltet (NAS-Mounts/Bind-Mounts), nicht MediaForge' Sicherungsgegenstand |
| `inbox` | Import-Eingang | flüchtig per Definition |
| `./.env` | lokale Konfiguration und Secrets | **kritisch**, winzig — gehört in jedes Betreiber-Backup, nie ins Git-Repository |

Medienpfade bleiben optional und können später als explizite Bind-/NFS-Mounts eingebunden werden. V0 verlangt keine Medien-Volumes und implementiert noch keine Bibliotheks-Marker- oder Schreiboperationen.

## Erstinstallation

V0-Entwicklungsablauf: (1) `.env.example` nach `.env` kopieren; (2) `make setup` ausführen oder die gleichwertigen Compose-Befehle aus dem Root-README verwenden; (3) Compose-, Migrations-, Test- und HTTP-Gates prüfen; (4) MediaForge unter `http://localhost:${MEDIAFORGE_PORT:-8100}` öffnen. Ein geführtes Web-Setup, produktive Release-Images und Connector-Konfiguration gehören nicht zum V0-Funktionsumfang.

## Upgrades

`docker compose pull && docker compose up -d` — dahinter der abgesicherte Ablauf aus [database/migrations.md](../database/migrations.md): Der neue `app`-Container läuft Pre-Flight (Schema-Fingerprint, Speicher, Pflicht-Backfills, Versions-Handshake) **vor** Migrationen; scheitert er, beendet sich der neue Container mit ausführlicher Meldung und der Betreiber startet den alten Tag (`MEDIAFORGE_VERSION` zurückstellen) — die Datenbank wurde nicht angefasst. Worker-Container alter Version gegen neue Datenbank: verhindert durch den Versions-Handshake (Worker prüfen die App-Schema-Version beim Start und bei Job-Annahme; Mismatch pausiert die Queue-Annahme statt Jobs auf falschem Schema laufen zu lassen). Release-Kanäle: `latest` (Stable), `vX.Y.Z` (Pinning, empfohlen), `edge` (Vorabversionen, ausdrücklich ohne Upgrade-Garantien).

## Betriebsprofile

Dokumentierte Skalierungs-Presets statt freier Schieberegler: **klein** (alles auf einem Host, 4 GB RAM: Worker-Parallelität halbiert via `.env`-Preset), **standard** (Referenz: 8 GB, Topologie wie ausgeliefert), **getrennt** (ai-worker auf zweitem Host: Redis/Postgres-Erreichbarkeit + Worker-Secret, [AI Engine](../modules/ai-engine.md) offener Punkt Transport-Härtung gilt). Ressourcen-Limits (`mem_limit`, `cpus`) sind in den Presets gesetzt — ein Analyse-Job-Ausreißer darf den Host nicht aushungern.

## Logs und Diagnose

Alle Dienste loggen strukturiert (JSON) nach stdout — Compose-üblich (`docker compose logs`), aggregierbar von Loki/Vector, ohne eigene Log-Infrastruktur vorauszusetzen. Korrelation über die Audit-`correlation_id` in Log-Kontexten (Fundament). `php artisan mediaforge:doctor` bündelt die Setup-Checks als CLI für Support-Fälle (Ausgabe ohne Secrets, kopierbar in Issues).

## Edge Cases

* **Host-Neustart mitten in Migration**: Migrationen sind transaktional bzw. guarded ([migrations](../database/migrations.md)); der Redis-Migrations-Lock verfällt per TTL; der nächste Start setzt sauber auf.
* **Volle Platte** (`pgdata`): Postgres stoppt Writes; die App meldet 503 mit klarer Ursache (Health-Check unterscheidet DB-voll von DB-weg); die Betriebs-Doku priorisiert `artifacts`-Aufräumen als Sofortmaßnahme (Housekeeping-Kommando).
* **NFS-Mount hängt** (statt fehlt): I/O-Timeouts statt Fehler — die Scan-Jobs haben Lese-Timeouts (Fundament), der Marker-Check nutzt `O_NONBLOCK`-äquivalente Prüfpfade der media-tools; ein hängender Mount degradiert die betroffene Bibliothek, nie den Stack.
* **Uhrzeit-Sprünge** (Host ohne NTP): `occurred_at`-Logik leidet systemweit; der Setup-Check und der Health-Monitor prüfen Zeitplausibilität (DB-Zeit vs. App-Zeit).
* **Windows/WSL2-Hosts**: unterstützt mit dokumentierten Einschränkungen (inode-Identität der Scans eingeschränkt → Pfad+Hash-Identität greift; Datei-Event-Semantik anders — reine Scheduler-Scans).

## Performance

Die Compose-Presets sind auf die Mengengerüste des Fundaments kalibriert (Referenz: 300k Items, 500k Dateien auf Standard-Profil). Postgres-Tuning liegt als kommentiertes `postgresql.conf`-Include bei (shared_buffers, maintenance_work_mem für HNSW-Builds — [Suche](../modules/search.md)); bewusst keine Auto-Tuning-Magie.

## Security

Deployment-seitige Härtung (das Gesamtkonzept in [architecture/security.md](security.md)): kein Dienst außer `app` exponiert; `app` nur localhost; interne Netze ohne Egress für Parser/ML; alle Container non-root mit read-only-Rootfs wo möglich (`app` braucht Schreibrechte nur auf tmp/cache-Mounts); Secrets ausschließlich via `.env`-Mount (nie in Images, nie in Compose-Environment-Blöcken, die in `docker inspect` lesbar wären — die `.env` wird ro gemountet und ist die dokumentierte Backup-/Rotations-Einheit); Image-Signierung und SBOM pro Release (Supply-Chain-Basis).

## Tests

Der Release-CI-Lauf enthält einen **Compose-Integrationstest**: frischer Stack aus den Release-Artefakten, Setup-Durchlauf per API, Smoke-Suite (Scan eines Fixture-Baums, ein Connector-Fake, ein Artefakt-Build), Upgrade-Test vom Vorrelease (Datenbank-Snapshot → neue Version → Pre-Flight/Migrationen/Smoke). Der `mediaforge:doctor`-Check läuft als Teil der Smoke-Suite.

## ADR-Verweise

[ADR-0001](../adr/0001-technology-stack.md), [ADR-0002](../adr/0002-modular-monolith.md), [ADR-0005](../adr/0005-immutable-originals.md) (ro-Mounts). Die FPM-Entscheidung schließt den offenen Punkt aus [architecture/overview.md](overview.md).

## Offene Punkte

* **Podman/rootless-Kompatibilität**: angestrebt, ungetestet — GPU-Passthrough und Netz-Isolation abweichend; Prüfauftrag vor Release.
* **ARM64-Images** (Heimserver-NAS mit ARM): Build-Matrix-Frage; media-tools-Abhängigkeiten (libbluray) sind ARM-verfügbar, ai-worker CUDA-los als CPU-Variante.
* **Automatische Updates** (Watchtower-Klasse): bewusst nicht empfohlen (Pre-Flight braucht Aufmerksamkeit bei Major-Sprüngen); eine differenzierte Empfehlung (Patch-Level ja, Minor nein?) braucht Release-Erfahrung.
