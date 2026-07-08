# AI Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (ai-worker, `ai`-Queue), [modules/audit.md](audit.md) (AI-Actor). Konsumenten: [Upscaler](audio-upscaler.md) (Worker-Protokoll hier normativ), [Assembler](audiobook-assembler.md) (Kapitelvorschläge), [Suche](search.md) (Embeddings), [Datenqualität](data-quality.md) (Vorschlags-Bewertungen).

## Motivation

MediaForge nutzt ML an mehreren Stellen — Audio-Restauration, Kapitelvorschläge, Embeddings für semantische Suche, perspektivisch Klassifikation. Ohne zentrale Engine entstünde pro Anwendungsfall ein eigener Python-Stack mit eigener Modellbeschaffung, eigenem GPU-Handling und — am gefährlichsten — eigener Interpretation der Architekturregel 5. Die AI Engine ist die eine kontrollierte Schnittstelle zwischen dem PHP-Monolith und allen ML-Modellen: Sie verwaltet Modelle als versionierte Ressourcen, betreibt das Worker-Protokoll und erzwingt technisch, dass jedes ML-Ergebnis mit vollständiger Herkunft (Modell, Version, Parameter, Confidence) und als nicht-offiziell gekennzeichnet ins System kommt.

## Problemstellung

**Herkunfts-Erzwingung.** Regel 5 („KI erfindet keine offiziellen Daten") ist nur haltbar, wenn sie nicht vom Wohlverhalten der Konsumenten abhängt. Die Engine muss so gebaut sein, dass ein Ergebnis ohne Modell-Identität und Kennzeichnung **technisch nicht existiert** — das Ergebnis-DTO trägt die Herkunft als Pflichtbestandteil, und die abnehmenden Actions validieren sie.

**Modell-Lebenszyklus.** Modelle sind große Binärdateien (hunderte MB bis GB) mit Lizenzen, Versionen und Hardware-Anforderungen. Beschaffung zur Laufzeit aus dem Internet widerspräche dem No-Egress-Sicherheitsmodell des Workers; Einbacken ins Image machte Images monströs und Updates träge.

**Heterogene Aufgabenprofile.** Ein Embedding dauert Millisekunden und läuft tausendfach; ein Hörbuch-Upscale dauert Stunden und läuft einzeln. Beide über dieselbe naive Queue zu schicken, ließe Embeddings hinter Stunden-Jobs verhungern.

**Optionalität.** MediaForge ohne jeden ai-worker muss vollständig funktionieren; jedes ML-Feature ist Zusatz, keines Voraussetzung (Masterdatei, Nicht-Ziele: keine verpflichtenden externen Dienste — das gilt auch für lokale GPU-Pflichten).

## Analyse bestehender Lösungen

**Immich** ist das direkte Vorbild (Masterdatei-Referenzanalyse): dedizierter ML-Container mit schmaler API, Modelle in einem Cache-Volume, CPU-Fallback — Architektur übernommen, Protokoll eigenständig (Immichs ML-API ist fototspezifisch). **Stash** zeigt Community-Modelle mit Plugin-Verteilung — und die Risiken unverifizierter Gewichte; daraus folgt die Hash-Pflicht der Registry. **Ollama/llama.cpp-Ökosystem** zeigt Modell-Registries mit Pull-Semantik; übernommen wird die Idee eines deklarativen Modell-Manifests, nicht die Laufzeit-Pull-Automatik (Egress-Verbot). LLM-Integrationen (Metadaten-Textgenerierung) sind bewusst **außerhalb** des Umfangs von Version 1: Die aktuellen Anwendungsfälle (Signalverarbeitung, Embeddings, Grenzerkennung) brauchen keine generativen Sprachmodelle, und deren Halluzinationsprofil wäre die schwerste Belastungsprobe für Regel 5 — vertagt, als offener Punkt dokumentiert.

## Architekturentscheidung

Drei Bausteine:

**Modell-Registry** (PHP, Tabelle + Manifest-Dateien): Jedes Modell ist ein Eintrag mit Task-Typ, Version, Gewichte-Hash, Hardware-Anforderungen (min. VRAM, CPU-fähig ja/nein), Lizenz-Kennung und Aktivierungsstatus. Gewichte liegen in einem dedizierten Modell-Volume (`/models`), das der Admin befüllt (dokumentierter Beschaffungsprozess pro Modell: Download-URL, erwarteter Hash — der Download geschieht außerhalb des Systems oder über einen expliziten, auditieren Admin-Job mit temporär erlaubtem Egress; Default ist manuelle Bereitstellung). Der Worker verifiziert Hashes beim Laden; Hash-Mismatch macht das Modell unbenutzbar (`integrity_failed`).

**Worker-Protokoll** (`ai-job/v1`, im [Upscaler](audio-upscaler.md) bereits normativ eingeführt und hier verallgemeinert): Worker konsumieren die `ai`-Queue, deklarieren beim Start ihre Fähigkeiten (verfügbare Modelle nach Hash-Prüfung, GPU/CPU, VRAM) in einem Registrierungs-Heartbeat (`ai_workers`-Tabelle, TTL-basiert), und bearbeiten Jobs nach Task-Klasse. Die Queue ist logisch nach **Gewichtsklassen** getrennt: `ai` (Schwerlast: Upscaling, Batch-Embeddings) und `ai-light` (Kurzläufer: Einzel-Embeddings, Klassifikationen) — ein Worker bedient konfigurierbar eine oder beide; damit verhungern Kurzläufer nicht (Queue-Topologie-Erweiterung gegenüber [architecture/overview.md](../architecture/overview.md), dort nachzutragen ist nur die Zeile — die Konventionen gelten unverändert).

**Ergebnis-Vertrag**: Jedes Worker-Ergebnis ist ein `AiResult`-DTO:

```php
final readonly class AiResult
{
    public function __construct(
        public string $taskType,          // 'chapter_proposal', 'embedding', 'audio_upscale_chunk'
        public ModelIdentity $model,      // name, version, weightsHash — Pflicht, nie nullable
        public array $params,             // normalisierte Parameter des Laufs
        public mixed $payload,            // taskspezifisch (Kapitelliste, Vektor, Chunk-Referenz)
        public ?float $confidence,
        public WorkerInfo $worker,        // Hardware, Laufzeit
    ) {}
}
```

Abnehmende Actions (`RequestAiChapterProposal`-Ergebnisverarbeitung, Embedding-Ingest, …) übernehmen `ModelIdentity` in die fachlichen Herkunftsfelder (`origin='ai'`, `origin_detail`, `source='ai'`) — die Felder sind in den Ziel-Schemata NOT NULL bzw. CHECK-bewehrt ([Assembler](audiobook-assembler.md): `chapter_sets`; [core-schema](../database/core-schema.md): `provider_ids.source`, `credits.source`). Der Audit-Actor während der Verarbeitung ist `ai:<model>@<version>` ([modules/audit.md](audit.md)).

## Alternativen

**ML in PHP** (ONNX-Runtime-Bindings o. ä.): unreif für die Task-Palette, GPU-Handling schmerzhaft — verworfen (Stack-Entscheidung). **Ein Prozess pro Task-Typ** (eigener Container je Modellfamilie): sauber isoliert, aber Betriebszoo für Heimserver; ein Worker-Image mit Task-Dispatch genügt, Isolation liefert der Prozess-Sandbox-Ansatz im Worker (Subprozess pro Job). **Externe API-Dienste** (Cloud-Inferenz): verletzt Self-Hosting-Grundsatz als Default; als optionaler Worker-Typ denkbar (Worker-Protokoll ist transportagnostisch), aber unspezifiziert und ausdrücklich nie Voraussetzung.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE ai_models (
    id             CHAR(26) PRIMARY KEY,
    name           TEXT        NOT NULL,            -- 'speech-bwe'
    version        TEXT        NOT NULL,            -- '2.1.0'
    task_type      TEXT        NOT NULL
        CHECK (task_type IN ('audio_upscale','chapter_proposal','embedding_text',
                             'embedding_audio','classification')),
    weights_hash   TEXT        NOT NULL,            -- blake3 der Gewichte-Datei(en)
    weights_path   TEXT        NOT NULL,            -- relativ zu /models
    min_vram_mb    INTEGER,
    cpu_capable    BOOLEAN     NOT NULL DEFAULT false,
    license        TEXT        NOT NULL,            -- SPDX-artig bzw. Freitext-Kennung
    status         TEXT        NOT NULL DEFAULT 'inactive'
        CHECK (status IN ('inactive','active','integrity_failed','deprecated')),
    notes          TEXT,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (name, version)
);

CREATE TABLE ai_workers (
    id             CHAR(26) PRIMARY KEY,            -- vom Worker generiert, stabil je Installation
    hostname       TEXT        NOT NULL,
    hardware       JSONB       NOT NULL,            -- GPU-Modell, VRAM, CUDA/CPU
    queues         TEXT[]      NOT NULL,            -- {'ai','ai-light'}
    available_models JSONB     NOT NULL DEFAULT '[]',  -- [{name,version}] nach Hash-Prüfung
    last_heartbeat_at TIMESTAMPTZ NOT NULL,
    registered_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

Aufgaben-Laufzeitdaten leben bei den Konsumenten (Upscaler: `upscale_runs`; Embeddings: Such-Modul) — die Engine hält bewusst keinen eigenen Job-Spiegel; die Queue plus `job_progress` (Fundament) genügen.

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `AiModel`, `AiWorker` | Model | Registry-/Heartbeat-Verwaltung |
| `ModelRegistry` | Service | `resolve(taskType, ?name): AiModel` (aktiv + auf mind. einem Worker verfügbar); `availability(taskType): Availability` für Feature-Gates der Konsumenten-UIs |
| `AiJobDispatcher` | Service | baut `ai-job/v1`-Payloads, wählt Queue nach Gewichtsklasse, hängt `checkpoint_key`/Affinität an |
| `AiResultConsumer` | Interface | Konsumenten registrieren Handler je `taskType`; die Engine validiert `AiResult`-Vollständigkeit **vor** Übergabe (fehlende ModelIdentity ⇒ Job-Fehler, nie stilles Durchreichen) |
| `RegisterAiModel`, `ActivateAiModel`, `DeprecateAiModel` | Action | Registry-Pflege (admin); Hash-Erwartung Pflicht; Audit |
| `PruneStaleWorkersJob` | Job (Scheduler) | Heartbeat-TTL; Status-Events fürs Health-Monitoring |

## API-Endpunkte

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/ai/models` | Registry mit Status und Worker-Verfügbarkeit | admin |
| `POST /api/v1/ai/models` / `PUT …/{ulid}` | Registry-Pflege | admin |
| `GET /api/v1/ai/workers` | verbundene Worker, Heartbeats, Auslastung | admin |
| `GET /api/v1/ai/availability?task=` | Feature-Gate-Abfrage (nutzen Konsumenten-UIs) | member |

## UI und Flows

**`Admin/AiEngine`** — Registry-Tabelle (Modelle, Versionen, Hash-Status, Lizenz), Worker-Panel (online/offline, Hardware, bediente Queues), Beschaffungs-Anleitung pro Modell (dokumentierter Hash + Ablageort; der „Verifizieren"-Button stößt die Worker-Hash-Prüfung an). Konsumenten-UIs (Upscaler-Profilwahl, Assembler-KI-Button, Such-Einstellungen) fragen `availability` ab und zeigen fehlende Voraussetzungen konkret („Modell speech-bwe 2.1.0 auf keinem Worker verfügbar") statt toter Buttons.

## Edge Cases

* **Worker mit veralteten Gewichten** (Modell-Update in Registry, Worker hat alte Datei): Worker meldet verfügbare `(name, version)`-Paare nach Hash-Prüfung — er bietet die alte Version schlicht weiter an, solange sie `active` ist; Jobs adressieren immer explizite Versionen (Profil-Snapshots beim Upscaler), nie „latest".
* **GPU-OOM bei parallelen Kurzläufern**: `ai-light`-Jobs deklarieren Speicherbedarf; der Worker serialisiert intern, wenn VRAM knapp wird (Semaphore) — die Queue-Parallelität bleibt 1 pro Worker und Gewichtsklasse.
* **Kein Worker, aber Jobs in der Queue**: Fundament-Regel — die Queue staut sichtbar (Health-Check „ai-Queue ohne Worker" im [Monitoring](health-monitoring.md)); Konsumenten-Actions prüfen `availability` **vor** dem Dispatch und lehnen mit klarer Meldung ab, statt ins Leere zu queuen (der Stau-Fall bleibt für Worker-Ausfall mitten im Betrieb reserviert).
* **Lizenz-Konflikte** (Modell mit nicht-kommerzieller Lizenz): Registry erzwingt eine Lizenz-Angabe und zeigt sie an; die Bewertung bleibt beim Betreiber — MediaForge ist kein Lizenz-Orakel, aber es versteckt die Frage nicht.

## Performance

Die Gewichtsklassen-Trennung ist die zentrale Maßnahme (Begründung oben). Modell-Warmhaltung: Worker halten das zuletzt genutzte Modell pro Gewichtsklasse geladen (LRU, VRAM-Budget); Batch-Embeddings amortisieren den Load über Batches (Dispatcher bündelt Embedding-Anforderungen, Default 64/Batch). Heartbeat-Intervall 30 s, TTL 90 s — Ausfallerkennung in < 2 min ohne Chatter.

## Security

Worker: kein Egress, read-only-Medien, Schreibrecht nur auf Task-Ausgabepfade, unprivilegiert (identisch zum media-tools-Modell). Gewichte-Integrität per Hash (Supply-Chain-Schutz: manipulierte Modelle fallen bei der Prüfung auf, nicht erst im Verhalten). Das Registrierungs-Protokoll der Worker läuft über das interne Netz mit Shared-Secret aus `.env` — ein fremder Prozess im Compose-Netz kann sich nicht als Worker ausgeben und Jobs (mit Medienpfaden) abgreifen. `AiResult`-Payloads werden als nicht vertrauenswürdige Eingaben validiert (Schema-Prüfung), bevor Konsumenten sie sehen — ein kompromittierter Worker kann falsche Vorschläge liefern (die per Regel 5 ohnehin nie automatisch aktiv werden), aber keine Injektionen in Actions tragen.

## Tests

Protokoll-Contract-Tests mit Fake-Worker (Registrierung, Heartbeat-Verlust, Hash-Mismatch, OOM-Meldung). Der zentrale Regel-5-Test: ein `AiResult` ohne ModelIdentity muss an der Engine-Validierung scheitern (konstruierbar nur über Serialisierungs-Manipulation — genau das simuliert der Test); ein durchgereichtes Ergebnis muss in den Zielschemata als `ai` ankommen (Stichproben über die Konsumenten-Contract-Tests). Availability-Gate-Tests (kein Worker ⇒ Konsumenten-Action lehnt ab).

## ADR-Verweise

Setzt um: Architekturregel 5 (technische Erzwingung), [ADR-0001](../adr/0001-technology-stack.md) (dedizierter Python-Worker), [ADR-0002](../adr/0002-modular-monolith.md) (Worker als eine der zwei legitimen Prozessgrenzen).

## Offene Punkte

* **Konkrete Modell-Empfehlungen** (welche Gewichte für `audio_upscale`/`embedding_text` ausgeliefert bzw. dokumentiert werden): bewusst außerhalb der Spezifikation (Modelllandschaft dreht schneller als das Handbuch); die Registry ist der stabile Rahmen. Eine gepflegte Empfehlungsliste gehört in die Betriebsdokumentation.
* **LLM-Integration** (Beschreibungs-Generierung, Metadaten-Extraktion aus Freitext): vertagt, siehe Analyse — braucht vor allem eine belastbare Anti-Halluzinations-Governance über Regel 5 hinaus.
* **Remote-Worker über Netz** (GPU-Rechner außerhalb des Compose-Hosts): Protokoll ist vorbereitet (Redis erreichbar machen + Secret), aber Transport-Härtung (TLS zum Redis) unspezifiziert.
