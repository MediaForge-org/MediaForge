# Plugin- und Extension-Entwicklung

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das Plugin SDK erweitert MediaForge lokal. Plugins können UI-Flächen, Backend-Actions, Workflows, Metadatenprovider, Importer, Health Checks oder Adult-/Jellyfin-/Audiobookshelf-Enhancements beitragen.

## Prinzipien

- lokale Installation ohne Cloudpflicht
- Versionierung und Kompatibilitätsprüfung
- klare Berechtigungen
- Audit für sicherheitsrelevante Aktionen
- deaktivierbar bei Inkompatibilität
- keine Umgehung von Sichtbarkeit, Secrets oder Rollen

## Erweiterungspunkte

- Dashboard-Widgets
- Metadata Provider
- Workflow Steps
- Rule Predicates und Actions
- Connector-Erweiterungen
- Health Checks
- UI-Komponenten innerhalb des Design-Systems

## Querverweise

Die technische Spezifikation steht in [../developer-handbook/plugin-sdk.md](../developer-handbook/plugin-sdk.md), wiederkehrende Muster in [../developer-handbook/contracts-reference.md](../developer-handbook/contracts-reference.md), Security-Grundlagen in [../architecture/security.md](../architecture/security.md).

## Akzeptanzkriterien

- Ein Plugin deklariert Fähigkeiten, Version und benötigte Berechtigungen.
- Inkompatible Plugins werden deaktiviert statt zur Laufzeit halb zu funktionieren.
- Plugins dürfen keine Sichtbarkeits-, Secret- oder Audit-Regeln umgehen.
- Plugin-UI nutzt das MediaForge-Design-System und leakt keine Restricted-Daten.
