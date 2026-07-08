# Prädikat- und Aktions-Referenz

Vertiefung zu [modules/rule-engine.md](../rule-engine.md). Normativer Katalog aller registrierten Prädikate und Aktionen, ihrer Parameter, SQL-Übersetzung und Gefahrenklasse. Das Modulkapitel definiert die Engine-Mechanik (Kompilierung, Dämpfung, Trace); dieses Dokument ist der Vertrag, den `GET /api/v1/rule-schema` an den `Rules/Builder` ausliefert — jedes Prädikat und jede Aktion hier ist 1:1 im Schema-Export vertreten.

## Prädikat-Vertrag

```php
interface PredicateInterface
{
    public function name(): string;                 // 'media.presence'
    public function subjectTypes(): array;           // ['media_item']
    public function paramsSchema(): ParamsSchema;     // typisierte Parameter fuer den UI-Builder
    public function toQuery(Builder $q, array $params): Builder;      // Batch-Pfad
    public function evaluate(Model $subject, array $params): bool;    // Event-Pfad
}
```

`ConditionCompiler` verifiziert bei jeder Prädikat-Registrierung (Boot-Zeit-Check, bricht das Deployment ab statt zur Laufzeit stillschweigend zu driften), dass `toQuery` und `evaluate` auf einer Zufallsstichprobe des Bestands übereinstimmen (derselbe Test, den das Modulkapitel als „wichtigsten Invariantentest" nennt, hier als Boot-Gate verschärft für neue Prädikate).

## Prädikat-Katalog

Gruppiert nach Subjekt-Typ. **SQL-Kosten**: I (indexgestützt, jeder Bestandsgröße zumutbar), J (Join-basiert, für Schedule-Regeln mit weiterem Vorfilter empfohlen), X (vom `ConditionCompiler` abgelehnt ohne vorangestelltes I-Prädikat in derselben `all`-Gruppe — Modulkapitel Performance: „lehnt Prädikat-Kombinationen ab, die zwangsläufig Seq-Scans erzeugen würden").

### `media_item`

| Prädikat | Parameter | Bedeutung | SQL-Kosten |
|---|---|---|---|
| `media.type` | `type: enum` | `media_type = ?` | I (`media_items_type_idx`) |
| `media.presence` | `presence: enum` | `presence = ?` | I |
| `media.library` | `library_id: ulid` | `library_id = ?` | I |
| `media.has_tag` | `namespace, name` | Join `taggables`/`tags` | J |
| `media.missing_field` | `field: enum(title,summary,year,…)` | `field IS NULL` | I (Teilindex je Feld für die häufig geprüften) |
| `media.age_days_since` | `field: enum(created_at,released_on), gte: int` | `now() - field >= interval` | I |
| `media.watch_status` | `status: enum, scope: 'any_user'|'user'` | Join `user_watch_states` | J |
| `media.provider_mapped` | `provider: enum` | Join `provider_ids` (`EXISTS`) | J |
| `media.quality_score_below` | `dimension: enum, threshold: numeric` | Join `quality_scores` | J |

### `file`

| Prädikat | Parameter | Bedeutung | SQL-Kosten |
|---|---|---|---|
| `file.status` | `status: enum` | `status = ?` | I |
| `file.candidate_type` | `type: enum` | `candidate_type = ?` | I |
| `audio.bitrate_below` | `kbps: int` | Join `files → analysis_report`-Artefakt (Bitraten-Feld) | J |
| `file.size_bytes` | `gte/lte: int` | `size_bytes BETWEEN` | I |

### `disc_image` (Disc-Engine-Prädikate, registriert vom `DiscEngineServiceProvider`)

| Prädikat | Parameter | Bedeutung | SQL-Kosten |
|---|---|---|---|
| `disc.mapping_status` | `status: enum(unmapped,partial,fully_mapped,review_open)` | abgeleitet aus `disc_playlists_class_idx`-Aggregat | J |
| `disc.analysis_status` | `status: enum` | `analysis_status = ?` | I |
| `disc.kind` | `kind: enum` | `disc_kind = ?` | I |

### `media_edition` (Assembler-/Upscaler-Prädikate)

| Prädikat | Parameter | Bedeutung | SQL-Kosten |
|---|---|---|---|
| `assembly.status` | `status: enum` | Join `audiobook_assemblies` | J |
| `assembly.sequence_confidence_below` | `threshold: numeric` | Join, `sequence_confidence < ?` | J |
| `edition.kind` | `kind: enum` | `edition_kind = ?` | I |
| `edition.is_upscale` | `bool` | `edition_kind = 'upscale'` | I |

### Universell (subjekttyp-agnostisch über den Morph-Mechanismus)

| Prädikat | Parameter | Bedeutung | SQL-Kosten |
|---|---|---|---|
| `tag.has` | `namespace, name` | wie `media.has_tag`, generisch über `taggable_type` | J |
| `age.days_since` | `field, gte` | generische Zeitfeld-Prüfung | I, sofern `field` indiziert ist (Registrierung verlangt eine Index-Deklaration je zugelassenes Feld) |

`X`-Kosten entstehen erst durch **Kombination**: `audio.bitrate_below` allein ist J (ein Join über einen begrenzten Subjekttyp); `audio.bitrate_below` als einziges Prädikat einer Schedule-Regel ohne vorangestelltes `media.library`/`media.type`-I-Prädikat wird vom Compiler abgelehnt (`rule.invalid_predicate`, Meldung nennt das fehlende Vorfilter-Prädikat) — die Ablehnung ist Boot-Zeit- bzw. Save-Zeit-Verhalten von `CreateRule`/`UpdateRule`, nie ein stiller Laufzeit-Seq-Scan.

## Bedingungsbaum: Grammatik

```json
{"all": [
  {"predicate": "media.type", "params": {"type": "audiobook"}},
  {"any": [
    {"predicate": "audio.bitrate_below", "params": {"kbps": 96}},
    {"not": {"predicate": "media.provider_mapped", "params": {"provider": "audible_asin"}}}
  ]}
]}
```

Validierungsregeln (`CreateRule`/`UpdateRule`): maximale Verschachtelungstiefe 4, maximale Blattzahl 20 (UI-Lesbarkeit und Compiler-Laufzeit), jedes `predicate`-Blatt muss registriert sein und `subjectTypes()` muss den `rules.subject_type` der Regel enthalten, Parameter werden gegen `paramsSchema()` typgeprüft (kein Freitext-Durchreichen in SQL — Modulkapitel Security).

## Aktions-Katalog

Gefahrenklassen: **A** (additiv/reversibel, z. B. Tag setzen), **B** (anstoßend, keine direkte Fachänderung, z. B. Job/Workflow starten), **C** (kommunizierend, keine Systemwirkung, z. B. Notify). Es gibt bewusst keine Klasse „D — fachlich entscheidend" — das ist die Grenze, die der Aktionskatalog nie überschreitet (Modulkapitel: „Nicht im Katalog und nie aufnehmbar").

| Aktion | Parameter | Klasse | Wirkung |
|---|---|---|---|
| `add_tag` | `namespace: 'rule', name` | A | `taggables`-Insert, `source='rule'` |
| `remove_tag` | `namespace: 'rule', name` | A | `taggables`-Delete im `rule`-Namespace |
| `create_review` | `task_type, priority` | A | `CreateReviewTask` (dedupliziert über den partiellen Unique-Index) |
| `start_workflow` | `definition_key, params` | B | `StartWorkflow` mit Subjekt als Kontext |
| `dispatch_job` | `job_class` (whitelisted Enum, s. u.) | B | direkter Job-Dispatch für Fälle ohne vollen Workflow |
| `notify` | `channel, template` | C | Rule-Engine-„notify"-Kanal (Modulkapitel offener Punkt: Kanal-Abstraktion liegt im Admin-/Monitoring-Umfeld) |

### `dispatch_job`-Whitelist (vollständig)

Nur folgende Jobs sind über `dispatch_job` erreichbar (jede Erweiterung ist ein Code-Review-Vorgang, Modulkapitel Security):

| Job | Zulässiger Subjekt-Typ |
|---|---|
| `ScanPathJob` | `file` (Pfad-Scope aus Subjekt-Kontext) |
| `RequestArrSearch` | `media_item` |
| `EnrichEntityJob` | `media_item` |

Nicht whitelisted und **niemals** hinzufügbar ohne Architektur-Ausnahme-Review: alles, was Watch-States, Mappings, Chapter-Set-Aktivierungen oder Löschungen auslöst — diese Liste ist bewusst kürzer als die Job-Gesamtreferenz, weil sie eine Teilmenge mit expliziter Sicherheitsprüfung ist, kein Spiegel des Job-Inventars.

## Trace-Format

```json
{
  "rule_id": "01J…", "subject": {"type": "media_item", "id": "01J…"},
  "condition_trace": {
    "all": [
      {"predicate": "media.type", "params": {"type": "audiobook"}, "result": true, "actual": "audiobook"},
      {"any": [
        {"predicate": "audio.bitrate_below", "params": {"kbps": 96}, "result": true, "actual": 64}
      ], "result": true}
    ], "result": true
  },
  "actions_executed": [
    {"action": "start_workflow", "params": {"definition_key": "upscale.request-and-notify"},
     "outcome": "started", "workflow_instance_id": "01J…"}
  ],
  "cooldown_check": {"last_fired_at": null, "cooldown_hours": 24, "allowed": true}
}
```

`actual` je Blatt ist die Pflichtangabe, die die Home-Assistant-inspirierte „warum feuerte das?"-Frage beantwortet (Modulkapitel) — ein Prädikat ohne `actual`-Wert in seiner `evaluate()`-Rückgabe ist ein Registrierungsfehler (Contract-Test erzwingt das Feld).

## Prüfliste für neue Prädikate/Aktionen (PR-Checkliste)

**Prädikat**: `subjectTypes()` korrekt · `toQuery`/`evaluate`-Äquivalenz-Test · SQL-Kosten-Einstufung (I/J/X) mit Begründung · `actual`-Wert in `evaluate()` · Zeile in diesem Katalog · Registrierung im Service Provider des Heimatmoduls. **Aktion**: Gefahrenklasse A/B/C zugewiesen · explizite Ablehnungsprüfung „ist das eine Fach-Entscheidung?" (wenn ja: gehört nicht hierher, sondern in eine Fach-Action mit Review-Pflicht) · bei `dispatch_job`: Whitelist-Eintrag mit Subjekt-Typ-Einschränkung · Zeile in diesem Katalog.
