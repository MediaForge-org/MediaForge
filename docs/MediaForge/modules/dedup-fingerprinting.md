# Dublettenerkennung und Fingerprinting

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md) (`files`-Hashes, Reviews), [modules/audio-analysis.md](audio-analysis.md) (Chromaprint-Stufe), [modules/disc-engine.md](disc-engine.md) (Struktur-Signaturen). Konsument: [Datenqualität](data-quality.md).

## Motivation

Große Sammlungen akkumulieren Duplikate: derselbe Film als Remux und als ISO, dasselbe Hörbuch aus zwei Quellen mit unterschiedlichen Bitraten, umbenannte Kopien nach Ordner-Reorganisationen. Stash (Masterdatei-Referenzanalyse) zeigt, dass konsequentes Fingerprinting das Identitätsfundament einer Medienverwaltung ist: Es macht Moves erkennbar (statt Löschung+Neuanlage), Duplikate findbar und Re-Rips derselben Quelle zuordenbar. Das Modul liefert beides — die **Fingerprint-Infrastruktur** (welche Kennungen, wann berechnet, wo gespeichert) und die **Dubletten-Fachlogik** (Kandidaten, Bewertung, Review, Auflösung).

## Problemstellung

**Identitätsstufen.** „Gleich" hat Stufen: byte-identisch (BLAKE3), inhaltsgleich trotz Container-Differenz (gleicher Audio-Strom in MP3-Kopie mit neuen Tags — Audio-Stream-Hash), wahrnehmungsgleich (gleiche Aufnahme in 128 vs. 320 kbit/s — Chromaprint), werkgleich (verschiedene Aufnahmen desselben Werks — **keine** Dublette, sondern Editionen!). Die Fachlogik muss diese Stufen unterscheiden; ein System, das werkgleich als Dublette meldet, terrorisiert Sammler von Fassungen.

**Kostenstaffelung.** BLAKE3 über 50 TB liest 50 TB. Hashing muss gestaffelt (Quick-Hash zuerst), inkrementell (nur Neues/Geändertes), gedrosselt (NAS-Schonung) und unterbrechbar sein.

**Auflösungs-Gefahr.** Dubletten-„Bereinigung" ist die gefährlichste Massenoperation des Systems (Löschen von Originalen!). Die Auflösung braucht dieselbe Disziplin wie alles Destruktive: Review-Pflicht, Karenz, Audit, keine Automatik.

## Analyse bestehender Lösungen

**Stash**: mehrstufige Hashes (oshash/MD5/pHash) pro Datei, Duplikat-UI mit Distanz-Schwellen — Vorbild für die Stufung und das Review-UI; nicht übernommen: automatische Lösch-Werkzeuge. **Immich**: pHash-basierte Duplikat-Erkennung mit Review-Flow — bestätigt das Review-Muster. **beets**: Akustik-Matching (Chromaprint/AcoustID) beim Import — Vorbild für die Wahrnehmungsstufe; AcoustID-Cloud-Lookup bleibt optional (Self-Hosting-Grundsatz: lokaler Vergleich zuerst). **fdupes/rmlint-Klasse**: Byte-Dedup ohne Medienverständnis — genau die Stufe-1-Basis, die MediaForge ohnehin über `files.content_hash` hat.

## Architekturentscheidung

**Fingerprints als eigene Tabelle** (nicht mehr Spalten an `files`): Eine Datei hat n Fingerprints verschiedener Typen und Versionen; Typen sind erweiterbar (Video-pHash später), Berechnungen versioniert wie beim Audioanalyse-Modul.

| Typ | Inhalt | Quelle | Identitätsstufe |
|---|---|---|---|
| `content_blake3` | Datei-Hash | Fundament-Scan (gespiegelt) | byte-identisch |
| `quick_xxh64` | Stichproben-Hash | Fundament-Scan | Vorfilter |
| `stream_audio_blake3` | Hash des dekomprimierten PCM-Streams normalisiert | media-tools | inhaltsgleich (Tag-/Container-invariant) |
| `chromaprint` | Audio-Wahrnehmungs-Fingerprint | [Audioanalyse](audio-analysis.md) | wahrnehmungsgleich |
| `disc_structure` | Struktur-Signatur (Spiegel aus `disc_images`) | [Disc-Engine](disc-engine.md) | Disc-inhaltsgleich |

Die **Dubletten-Pipeline** läuft ereignisgetrieben (`FileFingerprinted`): Neue Fingerprints werden gegen den Bestand geprüft — exakte Treffer per Index-Lookup, Chromaprint per segmentierter Ähnlichkeitssuche (Bit-Fehlerrate über alignierte Fenster; Schwelle Setting, Default 0.15). Treffer erzeugen `duplicate_groups` (Kandidatengruppen mit Stufe und Score), ab Konfidenz-Schwelle einen Review-Task `duplicate_suspect`. Zusätzlich konsumiert die Pipeline die Provider-ID-Kollisionen des Kernschemas (zwei Entitäten, gleiche externe ID — [ADR-0003](../adr/0003-provider-id-mapping.md)) als katalogseitiges Dubletten-Signal (Werk-Dublette statt Datei-Dublette).

**Auflösung** ist ausschließlich manuell über typisierte Actions: `MergeCatalogDuplicates` (zwei media_items verschmelzen: Watch-States vereinigen — jüngster Zustand gewinnt, Historien beider bleiben —, Provider-IDs/Credits/Tags zusammenführen, Verlierer-Item soft-deleted mit Verweis), `LinkAsEditions` (Dateien sind Fassungen desselben Werks ⇒ Editionen statt Dubletten — der Ausweg für die Werkgleich-Stufe), `DismissDuplicateGroup` (falsch-positiv, mit Begründung; die Gruppe wird für diese Fingerprint-Kombination nicht erneut gemeldet). **Datei-Löschung ist keine Funktion dieses Moduls** — MediaForge löscht keine Originale (Regel 4 sinngemäß); das UI zeigt Speicher-Redundanz und überlässt physisches Löschen dem Betreiber außerhalb, wonach der normale Scan den Zustand nachführt.

## Alternativen

**Auto-Merge ab hoher Konfidenz**: verworfen — Merges fassen Watch-States und Identitäten an; falsch-positive Auto-Merges sind kaum rückbaubar (die Kosten-Asymmetrie verbietet Automatik, dasselbe Argument wie Disc-Mapping). **AcoustID/MusicBrainz-Lookup als Primärweg**: Cloud-Abhängigkeit als Default verworfen; als optionaler Anreicherungsschritt (Werk-Identifikation, nicht Dublettenerkennung) beim Enrichment denkbar. **pHash für Video in Version 1**: verschoben — die dokumentierten Schmerzpunkte sind Audio und Byte-Ebene; Video-Wahrnehmungs-Hashing ist teuer und wartet auf Bedarf (offener Punkt). **Inline-Spalten statt Tabelle**: an Versionierung und Erweiterbarkeit gescheitert.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE file_fingerprints (
    id            CHAR(26) PRIMARY KEY,
    file_id       CHAR(26)    NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    fp_type       TEXT        NOT NULL
        CHECK (fp_type IN ('content_blake3','quick_xxh64','stream_audio_blake3',
                           'chromaprint','disc_structure')),
    fp_version    TEXT        NOT NULL DEFAULT '1',
    value         TEXT        NOT NULL,               -- Hex/Base64; Chromaprint komprimiert
    computed_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (file_id, fp_type, fp_version)
);

CREATE INDEX file_fingerprints_lookup ON file_fingerprints (fp_type, value);

CREATE TABLE duplicate_groups (
    id            CHAR(26) PRIMARY KEY,
    level         TEXT        NOT NULL
        CHECK (level IN ('byte','stream','perceptual','catalog')),
    score         NUMERIC(4,3) NOT NULL,
    status        TEXT        NOT NULL DEFAULT 'open'
        CHECK (status IN ('open','resolved_merged','resolved_editions','dismissed')),
    evidence      JSONB       NOT NULL DEFAULT '{}',   -- Treffer-Details, Distanzen
    resolved_by   CHAR(26)    REFERENCES users(id) ON DELETE SET NULL,
    resolved_at   TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE duplicate_group_members (
    group_id      CHAR(26) NOT NULL REFERENCES duplicate_groups(id) ON DELETE CASCADE,
    member_type   TEXT     NOT NULL,                  -- 'file' | 'media_item'
    member_id     CHAR(26) NOT NULL,
    PRIMARY KEY (group_id, member_type, member_id)
);

-- Dismissal-Gedächtnis: dieselbe Paarung nicht erneut melden
CREATE TABLE duplicate_dismissals (
    id           CHAR(26) PRIMARY KEY,
    member_a     CHAR(26) NOT NULL,
    member_b     CHAR(26) NOT NULL,
    level        TEXT     NOT NULL,
    reason       TEXT,
    dismissed_by CHAR(26) REFERENCES users(id) ON DELETE SET NULL,
    dismissed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (member_a, member_b, level)                -- normalisiert: a < b
);
```

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `FileFingerprint`, `DuplicateGroup` | Model | wie Schema |
| `ComputeFingerprintsJob` | ResumableJob (`analyze`) | gestaffelt: quick (Scan-inline) → content (Batch, I/O-gedrosselt via Token-Bucket pro Bibliothek) → stream/chromaprint (nur Audio, über Audioanalyse) |
| `FingerprintMatcher` | Service (pure) | `match(Fingerprint, Bestand): list<Match>` — Exakt-Lookup + Chromaprint-Distanz |
| `DetectDuplicatesJob` | Job (`default`) | Pipeline auf `FileFingerprinted`; Dismissal-Filter; Gruppen-Upsert |
| `ConsolidateMovedFileAction` | Action | Move-Erkennung (Fundament-Auftrag): gleicher content_blake3, alt missing/neu present ⇒ Pfad-Rewrite; Audit |
| `MergeCatalogDuplicates`, `LinkAsEditions`, `DismissDuplicateGroup` | Action | Auflösungen (oben); Merge in einer Transaktion mit vollem Audit-Diff beider Seiten |

## API und UI

API: `GET /api/v1/duplicates?level=&status=` (manager), `GET /api/v1/duplicates/{ulid}` (Gruppe mit Mitglieder-Details und Evidence), Auflösungs-POSTs analog den Actions. UI **`Duplicates/Review`**: Gruppenliste nach Stufe/Score; Gruppendetail als Gegenüberstellung (Dateien: Pfad, Größe, Qualitätsmetriken aus der Audioanalyse — die Entscheidungsgrundlage „welche Fassung ist besser"; Items: Metadaten-Diff, Watch-State-Bestand); Aktions-Buttons mit Konsequenz-Vorschau („Merge übernimmt 3 Watch-States, 12 Provider-IDs, löscht Item B soft"). Die Merge-Vorschau ist Pflicht-UI — kein Ein-Klick-Merge ohne Anzeige der Folgen.

## Edge Cases

* **Hardlinks/Reflinks** (gleiche Inode, zwei Pfade): kein Duplikat im Speichersinn — der Scan erkennt es über `inode_key` und die Gruppe wird mit `hardlink`-Hinweis in der Evidence gar nicht erst als Speicher-Redundanz gezählt.
* **Absichtliche Duplikate** (Export-Artefakte, Upscale-Editionen): Artefakt-Pfade sind per Definition ausgenommen (das Artefaktregister kennt die Herkunft); `LinkAsEditions`-Auflösungen erzeugen Dismissal-Gedächtnis.
* **Chromaprint bei Hörbüchern** (40 h): Fingerprint über die ersten/letzten 120 s pro Track plus Stichproben — Vollmaterial-Fingerprinting wäre sinnlos teuer; die Segment-Strategie steht in der `fp_version`-Definition.
* **Merge-Konflikt bei gelockten Feldern** (beide Items haben manuelle Locks mit verschiedenen Werten): Merge-UI erzwingt Feldwahl statt stiller Präzedenz.
* **Kollision der Provider-ID nach Merge** (der partielle Unique-Index): der Merge konsolidiert Provider-IDs vor dem Item-Merge in definierter Reihenfolge — Constraint-sicher in einer Transaktion.

## Performance

Content-Hashing ist der teuerste Batch des Systems: Token-Bucket-Drosselung pro Bibliothek (Setting MB/s, Default 100), Nachtfenster-Priorisierung via Scheduler, Wiederaufnahme per Datei-Checkpoint. Chromaprint-Vergleich: Kandidaten-Vorfilter über Längen-Buckets (±5 %) und exakte Prefix-Bits, dann paarweise Distanz — nie All-Pairs über den Bestand. `file_fingerprints_lookup` trägt die Exakt-Treffer; die Tabelle bleibt bei 500k Dateien × 4 Typen im einstelligen GB-Bereich.

## Security

Fingerprints sind inhaltsableitend, aber nicht invertierbar — dennoch gelten sie als Katalogdaten (`manager`-Sicht). Kein Cloud-Lookup ohne explizite Aktivierung (AcoustID wäre Datenabfluss über den Bestand). Die Auflösungs-Actions sind die kritische Fläche: `MergeCatalogDuplicates` verlangt `manager`, loggt vollständig und ist über die Soft-Delete-Karenz des Verlierer-Items faktisch rückholbar (Restore-Flow dokumentiert im Admin-Kapitel).

## Tests

Stufen-Matrix mit konstruierten Fixtures (Kopie, Re-Tag, Re-Encode 128/320, andere Aufnahme desselben Stücks ⇒ erwartete Stufe bzw. Nicht-Meldung). Move-Konsolidierung (Fundament-Szenario: Analyse-Ergebnisse und Watch-States überleben den Move). Merge-Transaktionstests (Watch-State-Vereinigung, Provider-ID-Reihenfolge, Lock-Konflikt). Dismissal-Gedächtnis. Drossel-Verhalten (Token-Bucket-Einhaltung unter Last).

## ADR-Verweise

[ADR-0003](../adr/0003-provider-id-mapping.md) (Kollisionen als Signal), [ADR-0005](../adr/0005-immutable-originals.md) (keine Datei-Löschung durch MediaForge), [ADR-0006](../adr/0006-action-level-audit.md) (Merge-Auditierung).

## Offene Punkte

* **Video-Wahrnehmungs-Hashing** (pHash über Keyframes): auf Bedarf verschoben; Tabellendesign ist vorbereitet (`fp_type`-Erweiterung).
* **AcoustID-Integration** als optionaler Anreicherungsschritt: Governance (Datenabfluss-Einwilligung) mit dem Enrichment-Thema klären.
* **Speicher-Redundanz-Bericht** (wie viel GB in Duplikaten): gehört ins Admin-Dashboard, Datengrundlage existiert hier.
