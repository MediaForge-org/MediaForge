# Kompatibilitätsrichtlinie

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

MediaForge muss mit zukünftigen Versionen von Jellyfin und Audiobookshelf möglichst stabil bleiben.

## Strategie

- lokale API-Versionen und Gegenstellen-Versionen erfassen
- Connector-Diagnostics vor Aktivierung und nach Updates
- Backups vor Migrationen und Updates
- Fallbacks bei fehlenden Feldern oder geänderten Endpunkten
- Health Checks nach Updates
- API-Änderungen als Compatibility Reviews erfassen
- optionale Upstream-Beiträge bevorzugen, wenn eine Funktion allgemein nützlich ist

Breaking Changes in MediaForge-APIs werden versioniert und mit paralleler Übergangszeit dokumentiert.

## Querverweise

API-Versionierung steht in [../api/conventions.md](../api/conventions.md), Connector-Diagnose in [../connectors/connector-sdk.md](../connectors/connector-sdk.md), Deployment- und Upgrade-Regeln in [../architecture/deployment.md](../architecture/deployment.md).

## Akzeptanzkriterien

- Jede Connector-Instanz kennt Gegenstellen-Version, erkannte Capabilities und letzten Diagnostics-Status.
- Updates starten mit Backup-/Preflight-Prüfung.
- Fallbacks sind dokumentiert, wenn eine API-Funktion fehlt oder sich ändert.
- Optional tiefe Integrationen werden nie zur Pflicht für den MediaForge-Kernbetrieb.
