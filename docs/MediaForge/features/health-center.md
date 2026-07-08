# Health Center

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das Health Center überwacht lokale Medien, Bibliotheken, Connectoren und MediaForge-Dienste.

## Prüfungen

- Dateiintegrität, Hashes und CRC
- beschädigte Dateien
- fehlende Cover, Untertitel oder Kapitel
- defekte Audiospuren
- fehlerhafte Metadaten
- Media Health Score
- Bibliotheks-Health-Score
- Warnungen, Reparaturvorschläge und Verlauf

Health-Ergebnisse werden als lokale Analyse- und Review-Daten gespeichert.

## Querverweise

Das Modulkapitel steht in [modules/health-monitoring.md](../modules/health-monitoring.md), der vollständige Check-Katalog in [modules/health-monitoring/health-check-reference.md](../modules/health-monitoring/health-check-reference.md). Abhilfen verweisen auf [developer-handbook/runbooks.md](../developer-handbook/runbooks.md).

## Akzeptanzkriterien

- Jeder Health Check hat Schwellen, Besitzer, Runbook-Verweis und Testfall.
- Massenbefunde werden gebündelt, damit Betreiber Ursachen statt Symptomlisten sehen.
- Checks dürfen keine Medien verändern; Reparaturen laufen über explizite Workflows oder Reviews.
- Adult-/Restricted-Befunde dürfen Details nur berechtigten Benutzern zeigen.
