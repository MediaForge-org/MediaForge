# PostgreSQL Source of Truth

## Verbindliche Entscheidung

PostgreSQL bleibt dauerhaft die kanonische Datenbank von MediaForge.

Aktueller Alpha-Stand: PostgreSQL 17.  
Zielpfad: Upgrade auf eine unterstützte PostgreSQL-18.x-Version in einer eigenen Infrastrukturphase, **nicht** mitten in V2-Facharbeit.

## Was PostgreSQL besitzt

- kanonische MediaForge-IDs;
- Media Items / Editions / Files;
- Provider- und Engine-Mappings;
- Benutzer und Rechte;
- Watch-/Listen-State-Koordination;
- Reviews und Audit;
- Metadaten-Provenienz und Source History;
- Collections;
- Adult-Domain-Identitäten und Zero-Leak-Rechte;
- Disc-Mappings/Verifikationsstatus;
- Background-Job-Checkpoints, soweit fachlich dauerhaft.

## Was PostgreSQL nicht ersetzen muss

Eine gebündelte Engine darf intern vorübergehend eigene Persistenz verwenden, solange:
- ihre IDs nur externe/Engine-IDs sind;
- MediaForge-Identität in PostgreSQL bleibt;
- Rebuild/Migration möglich ist;
- der Core nicht direkt in die Fremd-DB greift.

## Upgrade-Regel

Ein Major-Upgrade braucht:
1. vollständiges Backup;
2. Restore-Test in isolierter Instanz;
3. Extension-Kompatibilität (`pg_trgm`, `btree_gist`, `pgvector`);
4. Migrations-/Query-Test;
5. Performance-Baseline vor/nach;
6. Rollback-Plan.

Keine gleichzeitige Schema-Neukonzeption und Major-Version-Migration ohne zwingenden Grund.
