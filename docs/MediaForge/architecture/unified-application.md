# Unified Application Architecture

Status: verbindliche Zielarchitektur

## Produktgrenze

MediaForge ist **eine App mit einer Oberfläche, einer Domain/Origin, einem Auth-Modell und einem kanonischen Katalog**.

Der Nutzer soll nicht wissen müssen, ob Playback intern aus C#, Go oder Node kommt.

```text
Browser / Desktop / Mobile / TV
            |
        MediaForge
            |
   +--------+---------+
   |        |         |
 Video    Adult     Audio
 Engine   Engine    Engine
```

## Sichtbare UI

Normaler Modus kann zeigen:

- Home;
- Filme;
- Serien;
- Audiobooks;
- Podcasts;
- Collections;
- Search;
- Acquisition;
- Settings.

Adult ist im gesperrten Zustand **nicht sichtbar**. Nach Unlock wird `/adult/...` aktiv und dieselbe Design-Sprache verwendet.

## Eine Origin

Standard:

```text
http://localhost:8100
```

Gateway-Verteilung:

```text
/           React
/api/v1     Laravel
/_stream    Engine
```

## Katalog

PostgreSQL ist die einzige MediaForge Source of Truth. Engines liefern technische Spezialfunktionen, aber die sichtbare Identität und Relationen gehören MediaForge.

## Frontend

React Router besitzt die sichtbaren Routen. Inertia ist nur historischer Migrationsbestand und wird aus der Zielarchitektur entfernt.

## Engine-Ausfall

Teilweiser Ausfall degradiert Funktionen statt die gesamte App zu zerstören. Katalog bleibt lesbar, wenn Playback Engine temporär offline ist.

## Developer UIs

Originale Jellyfin/Stash/ABS UIs dürfen für Debug/Upstream-Vergleich existieren, werden aber nicht in normale Navigation eingebunden.

## Keine iframe-Integration

MediaForge baut eigene Seiten/Player/Workflows. Ein iframe ist keine Produktintegration.

## Monorepo

Alle Komponenten werden langfristig aus demselben GitHub-Repository gebaut. Details: [target-monorepo.md](target-monorepo.md).
