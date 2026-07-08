# Unified Dashboard

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das Unified Dashboard sammelt lokale Zustände aus MediaForge, Jellyfin und Audiobookshelf als anpassbare Widgets.

## Widgets

- Continue Watching
- Continue Listening
- Recently Added
- Recently Imported
- Recently Updated
- Trending
- Collections
- Empfehlungen
- Favoriten
- Wiedergabefortschritt und Hörfortschritt
- Speicherbelegung
- Systemzustand
- Hintergrundjobs
- Benachrichtigungen und Fehler
- Health Center
- Storage Analytics
- aktive Workflows
- letzte Backups
- offene Konflikte
- Metadatenwarnungen

Widgets respektieren Benutzerrechte, Adult-Sichtbarkeit und lokale Datenquellen.

## Querverweise

Die Dashboard-Engine steht in [modules/admin-dashboard.md](../modules/admin-dashboard.md), der Seitenkatalog in [ui/page-catalog.md](../ui/page-catalog.md), die UI-/UX-Ziele in [ui-ux/dashboard.md](../ui-ux/dashboard.md).

## Akzeptanzkriterien

- Jedes Widget benennt Datenquelle, Aktualisierungsrhythmus und Sichtbarkeitsregel.
- Widgets sind anpassbar und können später per Plugin SDK erweitert werden.
- Fehler- und Warnzustände verlinken auf Health Center, Review Inbox oder Runbook.
- Continue-Watching/-Listening nutzt lokale Sync-Daten, ohne Jellyfin oder Audiobookshelf zu ersetzen.
