# ADR-0011: Knowledge Graph relational in PostgreSQL

Status: accepted · Bezug: [modules/knowledge-graph.md](../modules/knowledge-graph.md)

## Kontext

Werkübergreifende Beziehungen (Adaption, Remake, Reihe, gleicher Stoff) bilden einen Graphen. Graph-Datenbanken (Neo4j-Klasse) sind das kanonische Werkzeug; die Masterdatei verlangt aber Begründung für jede Zweitdatenbank im Selfhosting-Kontext.

## Entscheidung

Der Graph lebt relational: eine `entity_relations`-Tabelle mit kuratiertem, gerichtetem Typkatalog (Inverse werden bei Abfrage gedreht, nie doppelt gespeichert; symmetrische Typen normalisiert), `work_series` als eigene Knoten mit Doppel-Ordnung (Publikation/Narrativ). Kanten tragen die volle Herkunfts-Disziplin (source, confidence, status suggested/confirmed, Evidence, Audit). Abfragen sind Nachbarschafts-Lookups (Tiefe ≤ 2) und Äquivalenzklassen-CTEs über kleine Cluster.

## Konsequenzen

* Keine Zweitdatenbank; Kanten sind transaktional konsistent mit dem Katalog und voll auditierbar mit Bordmitteln.
* Die Abfragemuster sind bewusst beschränkt (keine Pfadalgorithmen, Tiefenlimits, Kanten-Caps) — das ist Produktentscheidung, nicht nur Technik: Navigation statt Graph-Analytik.
* Typkatalog-Erweiterung ist Release-gebunden (CHECK-Migration), kein Betreiber-Freitext — verhindert Tag-Suppen-Wildwuchs.

## Revisionskriterien

Revision, wenn dokumentiert eintritt: Kantenzahl > ~1M mit Navigations-Latenzproblemen, oder ein reales Feature verlangt Pfad-/Zentralitätsalgorithmen. Fallback-Kandidat: eingebettete Graph-Erweiterung (Apache AGE) vor externem Neo4j.

## Erwogene Alternativen

Neo4j (Zweitsystem-Kosten, Backup/Sync), Kanten als Tags (keine Richtung/Herkunft), Reihen als Katalog-Container (vermengt Besitzhierarchie mit Wissensbeziehungen und zieht Watch-State-Semantik auf Reihen), freie Kantentypen (Wildwuchs).
