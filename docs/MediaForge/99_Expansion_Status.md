# Ausbau-Status — abgeschlossen bei ~395 Seiten (Nutzerentscheidung 2026-07-07)

**Dokumentationsrewrite 2026-07-08:** Der Ausbau-Status bleibt als historisches Arbeitsprotokoll erhalten. Die neue offizielle Ausrichtung ist MediaForge als lokale Enhancement Suite, eine lokale Enhancement Suite für Jellyfin und Audiobookshelf. Der Seitenumfang ist nicht mehr das Ziel; Konsistenz, lokale Architektur, Feature-Erhalt und klare Enhancement-Grenzen sind verbindlich.

**Entscheidung:** Der ursprüngliche Zielkorridor von 1200–1500 Seiten (3,0–3,75 MB) basierte auf einer Dichte-Kalibrierung, die sich in der Praxis als zu niedrig erwies (reale Vertiefungen sind inhaltsdichter als die ursprüngliche 2,5–3 KB/Seite-Schätzung). Nach 14 Batches und vollständiger Abdeckung aller ursprünglich geplanten Bereiche hat der Nutzer den aktuellen Stand (~1,09 MB, ~395 Seiten) als inhaltlich vollständig akzeptiert. Die verbleibenden 6 Reserve-Kandidaten (Szenario-Kochbücher, Daten-Wörterbuch, UI-Komponenten-Referenz, zwei Troubleshooting-Guides) werden nicht mehr verfolgt. Dieses Dokument bleibt als Arbeitsprotokoll erhalten.

---

# Ausbau-Status (ursprüngliches Ziel: 1200–1500 A4-Seiten)

Arbeitsdokument für den Tiefenausbau. Kalibrierung: ~2,5–3 KB Markdown ≈ 1 A4-Seite. Zielumfang gesamt: ~3,0–3,75 MB. Jede Vertiefungsdatei wird aus ihrem Modulkapitel verlinkt (Abschnitt „Vertiefungen"); die Masterdatei bleibt Einstieg.

| Bereich | Vertiefungsdateien | Ziel-KB | Status |
|---|---|---|---|
| Disc-Engine | modules/disc-engine/formats-bluray.md, formats-dvd.md, classification-rules.md, mapping-algorithm.md, playback-translation.md, api-reference.md, ui-reference.md, test-catalog.md | 250 | fertig (140 KB, dichter als kalkuliert) |
| Hörbuch-Assembler | modules/audiobook-assembler/sequencing-rules.md, chapter-source-formats.md, alignment-algorithm.md, artifact-builders.md, api-ui-tests.md | 200 | fertig (62 KB) |
| Audio-Upscaler | modules/audio-upscaler/worker-protocol.md, profiles-metrics.md | 80 | fertig (26 KB) |
| Enrichment (neues Modul) | modules/enrichment.md + modules/enrichment/provider-reference.md | 90 | fertig (33 KB; im Master-TOC verlinkt) |
| Datenbank | database/schema-reference.md, query-catalog.md | 150 | fertig (25 KB; inkl. user_container_progress-DDL + Partitions-Automatik, offene Punkte des Kernschemas gelöst) |
| API | api/endpoint-catalog.md, error-catalog.md | 130 | fertig (23 KB; ~90 Routen konsolidiert, Fehlercode-Namensraum-Register) |
| Architektur-Referenzen | architecture/jobs-reference.md, events-reference.md | 100 | fertig (22 KB; Job-Ressourcenkonfliktmatrix, Event-Fluss-Graph als Ersatz für verbotene Modul-Kopplungen) |
| Connectoren | connectors/<name>/api-mapping.md je Kern- bzw. optionalem Connector (jellyfin, abs, arr, optional stash, external-player) | 250 | fertig (39 KB; Wire-Ebene aller 5 Connectoren, Versionsmatrizen, Fixture-Indizes) |
| Engines | modules/workflow-engine/definitions-catalog.md, rule-engine/predicate-reference.md, search/embedding-spec.md, knowledge-graph/relation-reference.md | 160 | fertig (38 KB) |
| Betrieb | developer-handbook/runbooks.md, modules/health-monitoring/health-check-reference.md | 120 | fertig (28 KB; Runbook-Anker 1:1 an Check-Katalog gebunden) |
| UI-Handbuch | ui/design-system.md, ui/page-catalog.md | 120 | fertig (22 KB; neues Fundament-Kapitel, in Master-TOC verlinkt) |
| Developer Handbook | developer-handbook/coding-standards.md, module-cookbook.md, contracts-reference.md | 120 | fertig (33 KB; Kochrezept löst NFO-Export-Beispiel bis ins Detail durch) |
| Watch-State/Core-Vertiefung | modules/watch-state.md (Core-Modul ausformuliert), modules/review-system.md | 90 | fertig (36 KB; zwei neue vollständige Modulkapitel, in Master-TOC + core-schema.md/admin-dashboard.md verlinkt) |
| Reserve-Kandidaten (Dichte-Ausgleich) | architecture/settings-reference.md ✅, api/webhook-catalog.md ✅ fertig (39 KB); noch offen: modules/disc-engine/scenario-cookbook.md, modules/audiobook-assembler/scenario-cookbook.md, database/data-dictionary.md, ui/component-reference.md, developer-handbook/troubleshooting.md, connectors/troubleshooting.md | n. B. | teilweise |

Fortschritts-Log (Bytes gesamt nach Batch, via `find docs/MediaForge -name "*.md" | xargs wc -c`):

* Basis nach Gesamtbauplan: 549 KB (~200 Seiten)
* Batch 14 (Reserve, Teil 1: Settings- + Webhook-Gesamtkatalog): 1093 KB gesamt (~395 Seiten)
* Batch 13 (Watch-State/Review-System, 2 neue Modulkapitel): 1072 KB gesamt (~385 Seiten)

**Status nach 14 Batches**: Alle 13 ursprünglich geplanten Bereiche fertig, 2 von 8 Reserve-Kandidaten fertig. Reale Dichte pro Batch ~20–40 KB (Ø ~35 KB) statt der ursprünglich kalkulierten größeren Sprünge — bei 549 KB Basis und jetzt 1093 KB gesamt fehlen zum Zielkorridor (3,0–3,75 MB) noch ~1,9–2,7 MB, d. h. geschätzt **55–75 weitere Batches** dieser Art. Das ist ein Vielfaches des bisherigen Aufwands. Empfehlung an den Nutzer: entweder (a) Zielkorridor als „inhaltlich vollständig, aber dichter geschrieben als kalkuliert" akzeptieren (aktueller Stand deckt bereits jedes Modul, jede Engine, jeden Connector, Betrieb, UI und Entwicklerprozess normativ ab), oder (b) gezielt weitere Bereiche benennen, die noch vertieft werden sollen, oder (c) explizit den Auftrag bestätigen, weitere ~55–75 Batches age nach demselben Muster zu produzieren.
* Batch 12 (Developer Handbook, 3 Vertiefungen): 1036 KB gesamt (~375 Seiten)
* Batch 11 (UI-Handbuch, 2 Vertiefungen): 1002 KB gesamt (~365 Seiten) — 1-MB-Marke überschritten
* Batch 10 (Betrieb, 2 Vertiefungen): 980 KB gesamt (~355 Seiten)
* Batch 9 (Engine-Referenzen, 4 Vertiefungen): 952 KB gesamt (~345 Seiten)
* Batch 8 (Connector-API-Mappings, 5 Vertiefungen): 914 KB gesamt (~330 Seiten)
* Batch 7 (Architektur-Referenzen, 2 Vertiefungen): 878 KB gesamt (~320 Seiten)
* Batch 6 (API-Kataloge, 2 Vertiefungen): 855 KB gesamt (~310 Seiten)
* Batch 2 (Hörbuch-Assembler, 5 Vertiefungen): 753 KB gesamt (~270 Seiten)
* Batch 1 (Disc-Engine, 8 Vertiefungen): 691 KB gesamt (~250 Seiten). Erkenntnis: reale Dichte ~17 KB/Datei statt kalkulierter ~31 KB — Inhalte sind kompakter als die Ziel-KB-Spalte annahm. Konsequenz: Folgebatches erhalten zusätzliche Vertiefungsdateien je Bereich (statt Aufblähung bestehender), bis der Gesamtumfang 3,0–3,75 MB erreicht; Kandidaten sind am Tabellenende ergänzt.
