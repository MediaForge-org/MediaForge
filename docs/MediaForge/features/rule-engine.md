# Rule Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Die Rule Engine führt lokale Wenn-Dann-Regeln aus.

## Regelbereiche

- Medienanalyse nach Import
- Metadatenprüfung
- Backup-Regeln
- Qualitätsregeln
- Benachrichtigungen
- Konfliktregeln
- Bibliotheksregeln
- Adult-spezifische Regeln
- Audiobook-spezifische Regeln
- Jellyfin-spezifische Regeln

Regeln sind deklarativ, auditierbar und dürfen keine Sichtbarkeits- oder Security-Regeln umgehen.

## Querverweise

Die vollständige Engine-Mechanik steht in [modules/rule-engine.md](../modules/rule-engine.md), der normative Prädikat- und Aktionskatalog in [modules/rule-engine/predicate-reference.md](../modules/rule-engine/predicate-reference.md). Workflows werden nicht in Regeln eingebettet, sondern über registrierte Aktionen angestoßen; Details dazu stehen in [features/workflow-engine.md](workflow-engine.md).

## Akzeptanzkriterien

- Jede Regel nutzt registrierte Prädikate und Aktionen, kein Freitext-SQL und kein Scripting.
- Jede Regelentscheidung ist auditierbar und reproduzierbar.
- Adult- und Restricted-Inhalte werden nur mit passenden Grants ausgewertet.
- Automatische Aktionen müssen Review- oder Dämpfungsmechanismen respektieren, wenn Datenverlust oder Sichtbarkeitsrisiken entstehen.
