# Docker, Distribution und offizielles MediaForge Image

## Ziel

Für Benutzer existiert ein offizieller, dokumentierter Installationsweg:

```bash
docker compose up -d
```

Die Distribution darf intern mehrere Images verwenden, bleibt aber ein MediaForge-Produkt.

## Images

Langfristig etwa:

```text
ghcr.io/mediaforge-org/mediaforge-server
ghcr.io/mediaforge-org/mediaforge-web
ghcr.io/mediaforge-org/mediaforge-video
ghcr.io/mediaforge-org/mediaforge-adult
ghcr.io/mediaforge-org/mediaforge-audio
ghcr.io/mediaforge-org/mediaforge-media-tools
ghcr.io/mediaforge-org/mediaforge-ai
```

Optional kann `server + web` später in ein Gateway-/App-Image zusammengezogen werden, wenn Betrieb und Updates dadurch tatsächlich einfacher werden.

## Compose

`compose.yaml` ist die benutzerfreundliche Eintrittsstelle. Profile erlauben optionale schwere Komponenten:

```text
core      -> server, web, gateway, postgres, redis
video     -> video engine
adult     -> adult engine
audio     -> audio engine
ai        -> AI worker
downloads -> SABnzbd/qBittorrent optional
```

Ein normales All-in-One-Profil kann alles aktivieren.

## Gateway

Extern wird standardmäßig nur MediaForge veröffentlicht:

```text
http://localhost:8100
```

Intern routet der Gateway:

```text
/              -> web
/api/v1/*      -> server
/_stream/*     -> zuständige Playback Engine
/_internal/*   -> nur intern/gesichert
```

## GitHub Actions Release

Tag-Beispiel:

```text
v0.8.0
```

Pipeline:

1. Contract Tests.
2. PHP Tests + Static Analysis.
3. Web Typecheck/Lint/Tests/Build.
4. Go/C#/Node/Rust/Python Tests für betroffene Komponenten.
5. E2E Smoke Tests mit Compose.
6. Multi-Arch Buildx (`amd64`, später `arm64` soweit unterstützt).
7. SBOM erzeugen.
8. Images signieren/Provenance erzeugen.
9. GHCR pushen.
10. GitHub Release mit Checksums, Compose und Release Notes.

## Tags

```text
mediaforge-server:0.8.0
mediaforge-server:0.8
mediaforge-server:latest
```

`latest` wird nur für stabile Releases verwendet; Preview-Kanäle erhalten eigene Tags (`beta`, `nightly`).

## Kosten

Für ein öffentliches Open-Source-Projekt können GitHub Actions/GHCR je nach aktuellem GitHub-Plan sehr günstig oder für Standard-OSS-Workflows kostenfrei sein. Vor dem öffentlichen Release werden die dann geltenden GitHub-/Registry-Billing-Regeln geprüft. MediaForge darf keine Architekturannahme treffen, die zwingend kostenpflichtige Cloud-Dienste erfordert.

## Offline-/Local-first

Images werden einmal heruntergeladen; der normale Betrieb benötigt keinen MediaForge-Cloud-Account. Metadatenprovider benötigen natürlich Netzwerkzugriff, wenn sie benutzt werden.
