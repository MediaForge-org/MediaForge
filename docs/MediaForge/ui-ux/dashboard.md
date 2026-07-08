# Dashboard UX

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das Dashboard besteht aus anpassbaren Widgets und ist die lokale Startfläche für Medien, Systemzustand und offene Arbeit.

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
- Wiedergabe- und Hörfortschritt
- Speicherbelegung
- Systemzustand
- Jobs, Benachrichtigungen und Fehler
- Health Center
- Storage Analytics
- Workflows
- Backups
- Konflikte und Metadatenwarnungen

Widgets respektieren Sichtbarkeit, Adult-Grants und lokale Datenquellen.

## Querverweise

Produktanforderungen stehen in [features/unified-dashboard.md](../features/unified-dashboard.md), technische Umsetzung in [modules/admin-dashboard.md](../modules/admin-dashboard.md), Seitenstruktur in [ui/page-catalog.md](../ui/page-catalog.md).

## Akzeptanzkriterien

- Widgets lassen sich aktivieren, deaktivieren und perspektivisch umordnen.
- Continue Watching und Continue Listening führen in die jeweiligen Kernsysteme oder MediaForge-Detailflächen, ohne Playback neu zu implementieren.
- System-Widgets verlinken auf Health Center, Backup Center, Workflow Engine oder Developer Center.
- Restricted-Inhalte erscheinen nur bei expliziter Berechtigung.
