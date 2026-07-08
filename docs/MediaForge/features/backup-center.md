# Backup Center

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das Backup Center sichert lokale MediaForge- und Integrationsdaten.

## Sicherungsumfang

- MediaForge-Datenbank
- Laravel-Konfiguration
- Jellyfin-Konfiguration
- Audiobookshelf-Konfiguration
- Cover und Metadaten
- Plugins
- Logs
- Docker Compose
- relevante lokale Einstellungen

## Konzepte

Restore-Konzept, Backup-Zeitplan, Prüfsummen, lokale Backup-Ziele, optionale externe Ziele und Versionshistorie sind Teil des Moduls. Externe Ziele sind nur Optionen, nie Pflicht.

## Querverweise

Die vollständige Spezifikation steht in [modules/backup-restore.md](../modules/backup-restore.md). Deployment-Volumes und Upgrade-Schutzmechanismen stehen in [architecture/deployment.md](../architecture/deployment.md), Restore-relevante Runbooks in [developer-handbook/runbooks.md](../developer-handbook/runbooks.md).

## Akzeptanzkriterien

- Backups enthalten MediaForge-Datenbank, Konfiguration, relevante Connector- und Plugin-Daten sowie lokale Metadatenartefakte.
- Jeder Backup-Lauf erzeugt Prüfsummen und einen Restore-hinweisfähigen Katalog.
- Restore-Proben sind ein eigenes Feature, nicht nur eine Dokumentationsanweisung.
- Externe Backup-Ziele sind optional und müssen Datenabfluss transparent machen.
