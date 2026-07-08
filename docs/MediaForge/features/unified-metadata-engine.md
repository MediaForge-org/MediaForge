# Unified Metadata Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Die Unified Metadata Engine verbessert Jellyfin, Audiobookshelf und Adult Enhancement durch gemeinsame, nachvollziehbare Metadaten-Governance.

## Funktionen

- mehrere Metadatenquellen
- Prioritäten und Quellenvergleich
- Konfliktlösung und Merge-Assistent
- Metadaten-Versionierung
- Rollback und Änderungsverlauf
- lokale Overrides
- manuelle Korrekturen
- Import/Export
- Qualitätsbewertung

Providerdaten, Connector-Ingest, manuelle Werte und AI-Vorschläge bleiben unterscheidbar.

## Querverweise

Die detaillierte Merge- und Provider-Governance steht in [modules/enrichment.md](../modules/enrichment.md), die Provider-Referenz in [modules/enrichment/provider-reference.md](../modules/enrichment/provider-reference.md), das Datenmodell in [database/core-schema.md](../database/core-schema.md).

## Akzeptanzkriterien

- Jedes fremdbefüllte Feld trägt Herkunft und Zeitstempel.
- Manuelle Overrides werden nicht durch Provider-Refresh überschrieben.
- AI-Vorschläge bleiben als AI-Herkunft sichtbar und werden nie zu offiziellen Daten.
- Konflikte erzeugen Reviews statt stiller Überschreibung.
