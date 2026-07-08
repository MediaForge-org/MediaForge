# Stash: API-Mapping-Referenz

Diese Referenz beschreibt ausschließlich den optionalen lokalen Stash-Import. Stash ist kein Pflichtsystem und kein Kernbestandteil der Adult-Architektur. Alle Queries laufen gegen eine vom Betreiber konfigurierte lokale oder selbst gehostete Stash-Instanz.

Vertiefung zu [connectors/stash.md](../stash.md). Wire-Ebene für `StashGraphqlClient`/`StashSceneTranslator`: die einzige derzeit dokumentierte GraphQL-Gegenstelle, aber ausdrücklich optional. Normativ für die exakten Query-Dokumente (als Konstanten, keine dynamische Erzeugung — Modulkapitel Security), Feldpfade und die Fingerprint-Brücke.

## Versionsmatrix

| Stash-Version | Getestet | Bekannte Abweichungen |
|---|---|---|
| 0.20.x–0.24.x | ja (Fixture-Basis) | `o_counter` in 0.20 noch `Scene.o_counter`, ab 0.22 `Scene.o_history[]` (Zähler = Array-Länge) |
| 0.25.x+ | ja | `resume_time` in Sekunden-Float stabil über alle Versionen |
| < 0.20 | nicht unterstützt | GraphQL-Introspection-Check beim Diagnostics-Lauf schlägt fehl, wenn Kernfelder fehlen |

Der Client führt beim Verbindungstest eine Introspection-Query gegen den `Scene`-Typ aus und prüft die Existenz der benötigten Felder — GraphQL-Schemas sind selbstbeschreibend, das ersetzt eine harte Versionsnummer-Prüfung (robuster als bei den REST-Gegenstellen).

## Authentifizierung

```
Header: ApiKey: {api_key}
```

(Stash akzeptiert alternativ Cookie-Session; der Connector nutzt ausschließlich den API-Key-Header, Modulkapitel-Festlegung — kein Session-Handling.)

## Query-Dokumente (Konstanten)

Alle Queries liegen als statische GraphQL-Dokument-Strings im Client (keine String-Interpolation von Nutzereingaben in die Query selbst — nur Variablen-Bindings, Standard-GraphQL-Injection-Hygiene):

```graphql
query FindScenes($filter: SceneFilterType, $find_filter: FindFilterType) {
  findScenes(scene_filter: $filter, filter: $find_filter) {
    count
    scenes {
      id title date details
      files { path duration size fingerprint(type: "oshash") }
      o_history
      play_count
      resume_time
      play_duration
      performers { id name }
      studio { id name }
      tags { id name }
      updated_at
    }
  }
}
```

Variablen: `find_filter: {page, per_page: 200, sort: "updated_at", direction: ASC}`, `filter: {updated_at: {value: $cursor, modifier: GREATER_THAN}}` — die `updated_at`-Filterung ist der echte Cursor (Modulkapitel: „echter Cursor" im Gegensatz zu Jellyfin/ABS).

## Feld-Mapping: Szenen → Katalog

| Stash-Feld | Pfad | → Kanonisch | Anmerkung |
|---|---|---|---|
| `id` | root | `remote_ref` (`provider='stash_scene'`) | |
| `title` | root | `title` (nur bei Neuanlage; `restricted`-Items werden nicht enrichmentiert) | |
| `date` | root | `release_date` | ISO-Datum, direkte Übernahme |
| `details` | root | `summary` | |
| `files[].path` | Array, erstes Element maßgeblich | Pfad-Normalisierung gegen `files.path` | Multi-File-Szenen (seltene Re-Encodes) nutzen nur die Haupt-Datei |
| `files[].fingerprint(type:"oshash")` | Array | `file_fingerprints`-Import (`fp_type='stash_oshash'`) | Fingerprint-Brücke, s. u. |
| `performers[].name` | Array | `people` + `credits(role='actor')` | Namens-Upsert über `people.name`-Trigram-Vorabgleich, dann `provider_ids(provider='stash_performer')` |
| `studio.name` | Objekt | Tag im Namespace `stash:studio:` | |
| `tags[].name` | Array | Tags im Namespace `stash:tag:` | |
| `updated_at` | root | Cursor-Wert für den nächsten Lauf | |

## Feld-Mapping: Playstate

| Stash-Feld | Typ | → Kanonisch | Formel |
|---|---|---|---|
| `resume_time` | Sekunden-Float | `position_ms` | `round(resume_time * 1000)` |
| `play_duration` | Sekunden-Float | Plausibilisierung gegen `files[].duration` | analog ABS-Duration-Check |
| `play_count` | Integer | `play_count`-Untergrenze | monoton, wie Jellyfin |
| `o_history` (0.22+) / `o_counter` (< 0.22) | Array / Integer | **nicht übernommen** | Modulkapitel: kein MediaForge-Konzept, bewusste Nicht-Abbildung |

Egress-Mutation:

```graphql
mutation SceneSaveActivity($id: ID!, $resume_time: Float, $playDuration: Float) {
  sceneSaveActivity(id: $id, resume_time: $resume_time, playDuration: $playDuration)
}
```

Read-back: `FindScenes`-Query erneut mit `filter: {id: $id}`, Hash über `(resume_time, play_count)`.

## Fingerprint-Brücke

`files[].fingerprint(type:"oshash")` liefert Stashs eigenen Perceptual-artigen Hash (oshash — schneller Struktur-Hash, kein echtes pHash); zusätzlich verfügbar (nicht standardmäßig abgefragt, optional per Setting `import_phash`): `fingerprint(type:"phash")` und `fingerprint(type:"md5")`. Der Ingest-Handler importiert diese als zusätzliche `file_fingerprints`-Zeilen mit `fp_type ∈ {stash_oshash, stash_phash, stash_md5}` und **derselben** `file_id`, sofern das Pfad-Matching (Stufe 2) bereits eine MediaForge-Datei identifiziert hat — die Brücke setzt also Pfad-Matching voraus, sie ersetzt es nicht. Der praktische Nutzen (Modulkapitel): Eine zweite Kopie derselben Szene außerhalb der Stash-Bibliothek (andere MediaForge-Bibliothek) wird über identischen `oshash`/`md5` von der [Dublettenerkennung](../../modules/dedup-fingerprinting.md) gefunden, auch ohne dass MediaForge selbst je einen passenden Fingerprint-Typ berechnet hätte.

## Vertraulichkeits-Kopplung an die Wire-Ebene

Jede GraphQL-Antwort dieses Connectors wird vor der Persistierung in `connector_ingest_log` durch den Verschlüsselungs-Wrapper geleitet (Modulkapitel Security: App-Key-Verschlüsselung für `restricted`-Subjekte) — das gilt auch für die Rohantwort-Zwischenspeicherung während der Batch-Verarbeitung (In-Memory, nie unverschlüsselt auf Platte, auch nicht in Job-Queue-Payloads: `RunConnectorIngestJob` für diese Instanz serialisiert nur `remote_ref`-Listen in die Queue, nicht die vollen Szenen-Payloads).

## Fehlerklassifikation (`StashGraphqlClient`)

| Bedingung | Klasse | Health-Wirkung |
|---|---|---|
| HTTP 401 / GraphQL `errors[].message` enthält „Unauthorized" | permanent | `auth_failed` |
| GraphQL `errors[]` bei sonst validem HTTP 200 (GraphQL-Fehler-Konvention) | permanent (pro Query) | Diagnose-Log, Instanz bleibt `healthy` bei Einzelquery-Fehlern in Randfeldern |
| 429 / 5xx / Timeout | transient | `unreachable` nach 3 Fehlversuchen |
| Introspection fehlt Kernfeld | permanent | `unsupported_version` |

## Fixture-Index

`tests/fixtures/connectors/stash/{version}/`: `find-scenes.json` (inkl. Multi-File-Szene, fehlender `o_history`-Alt-Fall), `find-performers.json`, `introspection-scene-type.json` je Version. Sichtbarkeits-Regressionssuite (Modulkapitel) nutzt diese Fixtures als Ingest-Grundlage, prüft aber ausschließlich nachgelagerte Sichtbarkeit — nicht Teil dieser Wire-Referenz.
