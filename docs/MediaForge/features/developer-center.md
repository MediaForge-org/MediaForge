# Developer Center

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das Developer Center ist die lokale Diagnose- und Entwicklungsfläche für Betreiber, Maintainer und Plugin-Autoren.

## Funktionen

- Logs
- Queue und Jobs
- API Explorer
- Connector-Debugging
- Performance
- Datenbankstatus
- Cache-Status
- Worker-Status
- Test Center
- FFmpeg-Test
- Filesystem-Test
- Datenbank-Test
- Service-Test
- Connector-Test
- Plugin-Test

Das Developer Center arbeitet mit lokalen Diagnosedaten und darf keine Secrets offenlegen.

## Querverweise

Operative Abläufe stehen in [developer-handbook/runbooks.md](../developer-handbook/runbooks.md), Teststrategie in [developer-handbook/testing.md](../developer-handbook/testing.md), Coding-Konventionen in [developer-handbook/coding-standards.md](../developer-handbook/coding-standards.md). Connector-Diagnose nutzt [connectors/connector-sdk.md](../connectors/connector-sdk.md).

## Akzeptanzkriterien

- Secret-Felder werden maskiert und nie in Logs, Diagnoseausgaben oder Audit-Diffs ausgegeben.
- Jede Diagnosefläche verlinkt auf das passende Modulkapitel oder Runbook.
- Queue-, Worker-, Datenbank- und Connector-Status sind lokal einsehbar.
- Testläufe sind reproduzierbar und schreiben ihre Ergebnisse in auditierbare Operationen.
