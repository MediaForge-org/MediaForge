# ADR-0006: Audit auf Action-Ebene

Status: accepted · Bezug: [modules/audit.md](../modules/audit.md), Architekturregel 6

## Kontext

Jede Änderung in MediaForge muss auditierbar sein: Verursacher (User/Job/Connector/AI), Wirkung (Vorher/Nachher), Kausalität (was löste was aus). Etablierte Laravel-Pakete auditieren über Eloquent-Model-Events; Event Sourcing würde Audit als Nebenprodukt liefern.

## Entscheidung

Auditiert wird auf Ebene fachlicher Operationen (Actions), nicht auf Ebene von Modell-Zeilen. Die Basisklasse `AuditableAction` schreibt `audit_operations` (Vorgang, Actor, Kausalkette, correlation_id) und `audit_entries` (Entitätswirkungen mit Feld-Diffs) in derselben Transaktion wie die Fachänderung. Actor-Auflösung erfolgt über einen Kontext-Stack, der an den Systemgrenzen gesetzt und über Job-Dispatches propagiert wird. Beide Tabellen sind append-only (REVOKE UPDATE/DELETE), monatlich partitioniert. Domänen mit eigener append-only-Historie (watch_state_events) auditieren nur Zustandsübergänge, nicht Fortschreibungen.

## Konsequenzen

* Massenoperationen sind ein Vorgang mit N Positionen — abfragbar als Operation und pro Subjekt.
* Der Vollständigkeitsanker verschiebt sich vom ORM in die Architekturregel „Schreiben nur über Actions" — deshalb sind die Architektur-Tests gegen Schreibpfade an Actions vorbei verpflichtend.
* Bulk-SQL innerhalb von Actions bleibt auditierbar (die Action beschreibt die Wirkung), während es Model-Event-Systeme stumm umgehen würde.
* Kausalketten (`correlation_id`, `caused_by`) machen mehrstufige Pipelines (Scan → Analyse → Mapping) als Zusammenhang rekonstruierbar.

## Erwogene Alternativen

Model-Event-Pakete (falsche Granularität, Bulk-Lücken, kein Vorgangskontext), generisches Trigger-Audit (lückenlos, aber ohne Actor/Kausalität; als späteres Sicherheitsnetz denkbar), Event Sourcing (Paradigmenkosten für das Gesamtprojekt unverhältnismäßig), externe Log-Pipeline (Logs sind kein transaktionaler Referenzbestand).
