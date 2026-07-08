# Workflow Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Die Workflow Engine orchestriert lokale Verarbeitungsketten.

## Workflow-Typen

- Import-Workflows
- Scan-Workflows
- Metadaten-Workflows
- Backup-Workflows
- Health-Check-Workflows
- manuelle Ausführung
- automatische Ausführung
- Queue-Unterstützung
- Fehlerbehandlung
- Wiederholung
- Statusanzeige
- Logs

Workflows verbinden vorhandene Module, ohne Jellyfin oder Audiobookshelf nachzubauen.

## Querverweise

Die vollständige Spezifikation steht in [modules/workflow-engine.md](../modules/workflow-engine.md), der Katalog versionierter Definitionen in [modules/workflow-engine/definitions-catalog.md](../modules/workflow-engine/definitions-catalog.md). Jobs und Queues sind in [architecture/jobs-reference.md](../architecture/jobs-reference.md) konsolidiert.

## Akzeptanzkriterien

- Jede Workflow-Definition ist versioniert und laufende Instanzen behalten ihre Version.
- Jeder Schritt ist idempotent oder hat einen dokumentierten Kompensationspfad.
- Manuelle Reviews blockieren oder verzweigen explizit; stille Weiterläufe an offenen Entscheidungen vorbei sind verboten.
- Status, Fehler, Wiederholungen und Logs sind im Dashboard und Developer Center nachvollziehbar.
