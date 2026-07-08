# ADR-0010: Hybride Suche in PostgreSQL statt externem Suchserver

Status: accepted · Bezug: [modules/search.md](../modules/search.md)

## Kontext

MediaForge braucht lexikalische Suche (exakte Titel, Tippfehler-Toleranz) und semantische Suche (Bedeutungsnähe über Embeddings). Meilisearch/Typesense böten beides als Fertiglösung; PostgreSQL bietet FTS, pg_trgm und pgvector.

## Entscheidung

Beide Suchstufen laufen in PostgreSQL: FTS (`tsvector`, german+simple) plus Trigram lexikalisch, pgvector (HNSW, Cosine) semantisch, fusioniert per Reciprocal Rank Fusion (rangbasiert, keine Score-Normalisierung). Embeddings sind modell- und textversioniert; nur eine aktive Modellversion bedient die Suche; KI-herkünftige Felder werden nie eingebettet (Regel 5). Ohne verfügbaren AI-Worker degradiert die Suche deklariert auf lexikalisch.

## Konsequenzen

* Kein zusätzlicher Suchserver im Compose-Stack (Backup, Updates, Konsistenz-Synchronisation entfallen) — konsistent mit der Ein-Datenbank-Entscheidung (ADR-0001).
* Suchindex ist transaktional konsistent mit dem Katalog (generierte Spalte, kein Sync-Lag).
* RRF vermeidet die Fragilität von Score-Normalisierung über inkommensurable Skalen.
* Grenzen akzeptiert: Facetten-Berechnungen und Typo-Toleranz sind handgebaut statt geschenkt; Relevanz-Tuning ist SQL-Arbeit.

## Revisionskriterien

Die Entscheidung wird revidiert, wenn dokumentiert eintritt: lexikalische p95-Latenz > 200 ms trotz Index-Tuning bei Referenzgröße (300k Items), oder Facetten-/Ranking-Anforderungen, die in SQL unwartbar werden. Fallback-Kandidat: Meilisearch als reiner Lese-Index; Postgres bleibt der Referenzspeicher.

## Erwogene Alternativen

Meilisearch/Typesense (Zweitsystem-Kosten, Sync-Komplexität), Elasticsearch (Overkill), nur-semantisch (exakte Anfragen leiden), Score-Normalisierung statt RRF (Verteilungsdrift-fragil).
