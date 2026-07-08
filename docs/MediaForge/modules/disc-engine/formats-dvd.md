# Formatreferenz DVD-Video (VIDEO_TS)

Vertiefung zu [modules/disc-engine.md](../disc-engine.md), Abschnitt „Disc-Format-Grundlagen". Gleicher Vertragscharakter und gleiche Dreiteilung (Binärformat / Extraktion / Modell) wie die [Blu-ray-Referenz](formats-bluray.md). DVD ist das ältere und in mancher Hinsicht bösartigere Format: Die Navigationsstruktur ist verzweigter (PGC-Programmierung mit echten Sprungbefehlen), die Zeitangaben sind BCD-codiert mit Framerate-Kopplung, und zwanzig Jahre Authoring-Wildwuchs haben mehr Anomalien hervorgebracht als bei BD. Entsprechend defensiver ist die Extraktion spezifiziert.

## Dateisystem-Ebene

DVDs verwenden das **UDF-Bridge-Format**: UDF 1.02 *und* ISO 9660 zeigen auf dieselben Extents. Der Analyzer (libdvdread) liest bevorzugt UDF und fällt auf ISO 9660 zurück (`fs_layer: "udf"|"iso9660"` im JSON — reine Diagnose; manche uralten Rips haben beschädigte UDF-Deskriptoren bei intaktem ISO-Pfad). ISO-Zugriff wie bei BD ohne Mount, direkt aus der Image-Datei. Volume-Label aus dem Primary Volume Descriptor (ISO) bzw. Logical Volume Identifier (UDF); bei Differenz gewinnt UDF, die Differenz ist Anomalie `label_mismatch`.

Die 1-GiB-Grenze der VOB-Dateien (`VTS_01_1.VOB`, `VTS_01_2.VOB`, …) ist ein ISO-9660-Level-Artefakt: Ein Title Set ist **eine** logische VOB-Sequenz, die Dateigrenzen sind bedeutungslos. Der Analyzer summiert die Segmentgrößen je VTS; die Engine sieht nur Gesamtgrößen.

## Verzeichnisstruktur: vollständige Referenz

| Pfad | Pflicht | Analyzer-Verhalten |
|---|---|---|
| `VIDEO_TS/VIDEO_TS.IFO` (VMGI) | ja | vollständig geparst — Einstieg |
| `VIDEO_TS/VIDEO_TS.BUP` | ja | Backup; nur bei korruptem IFO (Anomalie `used_backup`) |
| `VIDEO_TS/VIDEO_TS.VOB` | nein | First-Play/VMG-Menü-Video; nur Existenz |
| `VIDEO_TS/VTS_nn_0.IFO` (VTSI, nn = 01–99) | ≥ 1 | vollständig geparst — Kernextraktion |
| `VIDEO_TS/VTS_nn_0.BUP` | ja | Backup wie oben |
| `VIDEO_TS/VTS_nn_0.VOB` | nein | VTS-Menü-Video; Existenz ⇒ Menü-Fähigkeit |
| `VIDEO_TS/VTS_nn_k.VOB` (k = 1–9) | ≥ 1 | `stat()` je Segment, summiert |
| `AUDIO_TS/` | nein | DVD-Audio; leer oder ignoriert (nicht unterstützt, Hinweis `audio_ts_content`) |
| `JACKET_P/` | nein | Jacket-Pictures; referenziert als Cover-Kandidaten (analog META bei BD) |

Ein `VIDEO_TS/` ohne `VIDEO_TS.IFO` (auch nach BUP-Versuch) ist keine DVD-Struktur ⇒ `analysis_status='failed'`.

## VMG (Video Manager) — VIDEO_TS.IFO

### Binärformat

Big-Endian, Sektor-adressiert (2048-Byte-Logikblöcke). Kopf `DVDVIDEO-VMG`. Relevante Tabellen über Sektor-Pointer: **VMGI_MAT** (Management Table: Versionsnummer, Kategorie/Region-Maske, Anzahl VTS, Provider-ID, POS-Code), **TT_SRPT** (Title Search Pointer Table: die disc-weite Titelliste), **VMGM_PGCI_UT** (Menü-PGCs des VMG je Sprache), **VTS_ATRT** (Attribut-Spiegel aller VTS), **TXTDT_MG** (Textdaten, praktisch nie gepflegt).

**TT_SRPT** ist der Dreh- und Angelpunkt der Titel-Navigation. Je Titel: `title_playback_type` (Bitfeld: sequentielle PGC vs. mehrere PGCs, Befehle in PGCs erlaubt, JLC/UOP-Flags), `number_of_angles`, `number_of_ptts` (Kapitel/„Part of Title"-Zahl), `parental_id`, `vts_number`, `vts_ttn` (Titelnummer innerhalb des VTS), `title_set_sector`.

### Extraktion

```json
"vmg": {
  "version": "1.1",
  "region_mask": 0,
  "provider_id": "WARNER_HV",
  "num_vts": 3,
  "titles": [
    {"title": 1, "vts": 1, "vts_ttn": 1, "ptts": 24, "angles": 1,
     "playback_type": {"sequential_pgc": true, "commands_in_pgc": true}},
    {"title": 2, "vts": 2, "vts_ttn": 1, "ptts": 6, "angles": 1,
     "playback_type": {"sequential_pgc": true, "commands_in_pgc": false}}
  ],
  "menu_languages": ["de", "en"]
}
```

`region_mask = 0` heißt regionfrei; die Maske ist ein Bitfeld gesperrter Regionen (Bit n = Region n+1 gesperrt). `provider_id` (8 ASCII-Zeichen, oft Studio-Kürzel) und die Disc-Kategorie fließen in `native_disc_id` ein: `"{provider_id}:{vmg_pos_code_hex}"`, sofern beide nicht leer/null sind — wie bei BD rein informativ, nie Primäridentität (Architekturregel 7).

### Modell

Die Titelliste dient wie bei BD als Reporting-Brücke: Player im `title_only`-Modus melden DVD-Titelnummern; `title_playlist_hints` bildet Titel → PGC-Playlist ab. Bei DVDs ist diese Abbildung fast immer eindeutig (`sequential_pgc`-Titel zeigen auf genau eine PGC via VTS_PTT_SRPT) — der Hint-Deckungsgrad ist bei DVD deutlich höher als bei BD, was `title_only`-Reporting für DVDs praktisch vollwertig macht.

## VTS (Video Title Set) — VTS_nn_0.IFO

### Binärformat

Kopf `DVDVIDEO-VTS`. Tabellen: **VTSI_MAT** (Management), **VTS_PTT_SRPT** (Part-of-Title Search Pointer: Kapitel → PGC/Programm-Zuordnung je Titel), **VTS_PGCIT** (Program Chain Information Table — die Kernstruktur), **VTSM_PGCI_UT** (Menü-PGCs des VTS je Sprache), **VTS_TMAPTI** (Time Map Table: Zeitpunkt → VOBU-Adresse, für Zeitsuche), **VTS_C_ADT** (Cell Address Table), **VTS_VOBU_ADMAP** (VOBU Address Map).

**PGC (Program Chain)** — das DVD-Pendant der Playlist — enthält: `nr_of_programs`, `nr_of_cells`, `playback_time` (BCD, siehe Zeitmodell), Prohibited-User-Operations, Audio-/SubPicture-Stream-Steuerung, `next_pgc`/`prev_pgc`/`goup_pgc`-Verkettung, **Command-Tabellen** (pre/post/cell commands — echte VM-Befehle), **Program Map** (Programm n beginnt bei Zelle m), **Cell Playback Info Table** (je Zelle: Kategorie-Bits, `playback_time`, First/Last-VOBU-Sektoradressen), **Cell Position Info Table** (je Zelle: VOB-ID, Cell-ID im VOB).

Die **Zellen-Kategorie** trägt die Multi-Angle-Mechanik: `block_mode` (kein Block / erster / mittlerer / letzter Winkel im Block) und `block_type` (Angle-Block), dazu `seamless_play`, `interleaved`, `stc_discontinuity`, `seamless_angle`. Ein Angle-Block aus k Zellen ist **eine** logische Abspielposition mit k Varianten.

### Extraktion

Der Analyzer expandiert je VTS alle Titel-PGCs (aus VTS_PGCIT, erreichbar über VTS_PTT_SRPT) und alle Menü-PGCs (aus VTSM_PGCI_UT, nur gezählt). Je Titel-PGC:

```json
"playlists": [{
  "ref": "VTS02/PGC01",
  "title_hint": 2,
  "duration_ms": 2578120,
  "framerate": "25",
  "programs": [1, 4, 9, 15, 21],
  "cells": [
    {"seq": 1, "vob_id": 1, "cell_id": 1, "duration_ms": 421000,
     "clip_ref": "VTS02/VOB01/CELL01", "angle_block": null,
     "seamless": true, "interleaved": false, "stc_discontinuity": false},
    {"seq": 2, "vob_id": 1, "cell_id": 2, "duration_ms": 388120,
     "clip_ref": "VTS02/VOB01/CELL02", "angle_block": {"position": "first", "size": 3},
     "seamless": true, "interleaved": true, "stc_discontinuity": false}
  ],
  "commands": {"pre": 4, "post": 2, "cell": 0},
  "next_pgc": null, "prev_pgc": null,
  "uo_mask_significant": false,
  "audio": [{"stream": 0, "lang": "de", "codec": "ac3", "channels": 6},
            {"stream": 1, "lang": "en", "codec": "ac3", "channels": 6}],
  "subpictures": [{"stream": 0, "lang": "de"}, {"stream": 1, "lang": "en"}]
}]
```

`programs` ist die Program Map als Liste der Startzellen-Indizes; Kapitelmarken entstehen daraus (siehe Modell). `commands` liefert nur Zählwerte — die VM-Befehle selbst werden nicht exportiert (siehe „Nicht modelliert"), aber PGCs mit Post-Command-Verkettung auf andere PGCs werden als `chained_to` gemeldet, wenn der Befehl ein statischer `JumpSS/LinkPGCN` ist (das häufigste Muster: Episoden-PGCs, die nach Abspann zurück ins Menü oder zur nächsten Episode springen — Letzteres ist ein Play-All-Signal, Regel R-04-Evidenz in [classification-rules.md](classification-rules.md)).

### Modell

Abbildungsregeln (normativ, Erweiterung der Modulkapitel-Tabelle):

| DVD-Konzept | Engine-Modell | Regel |
|---|---|---|
| Titel-PGC | `disc_playlists` (`playlist_ref = "VTSnn/PGCkk"`) | jede über TT_SRPT erreichbare PGC; verwaiste PGCs (in keiner Titel-Navigation) werden mit `orphan`-Evidenz trotzdem erfasst |
| Menü-PGC (VMGM/VTSM) | `disc_menus` (menu_kind `dvd_vmg`/`dvd_vts`, `menu_ref = "VTSnn"` bzw. `"VMG"`) | gezählt, nicht strukturell expandiert |
| Zelle | `disc_playlist_items` (seq = Zellreihenfolge) + `disc_clips` | Angle-Blocks: **eine** Item-Zeile für den Block, `angle_count = Blockgröße`, `clip_ref` des ersten Winkels |
| Zellen desselben VOB-Bereichs in mehreren PGCs | **ein** `disc_clips`-Eintrag, mehrfach referenziert | `clip_ref = "VTSnn/VOBii/CELLjj"` ist der natürliche Schlüssel; identische (vts, vob_id, cell_id) ⇒ derselbe Clip |
| Programm-Grenzen | `disc_playlist_marks` | Marke an der kumulierten Startzeit jeder Programm-Startzelle; Marke 1 bei 0 ms |
| PTT (Part of Title) | — | nicht separat modelliert; PTTs zeigen auf Programme, die Marken tragen die Information |
| In-/Out-Time | `in_time_ms = 0`, `out_time_ms = cell.duration_ms` | DVD-Zellen werden immer vollständig gespielt — es gibt kein PlayItem-Schnittfenster wie bei BD |

Die letzte Regel ist der strukturelle Hauptunterschied zu BD: **DVD-PGCs schneiden nicht in Zellen hinein.** Unterschiedliche Schnittfassungen realisiert DVD über unterschiedliche Zellen-Sequenzen (und Angle-Blocks), nie über In/Out-Fenster. Für die Engine heißt das: `disc_playlist_items.in_time_ms` ist bei DVD immer 0 — die Spalte bleibt aus Modell-Einheitlichkeit, die Invariante `out > in` gilt unverändert.

## Zeitmodell (normativ)

DVD-Zeiten (`dvd_time_t`) sind 4 Bytes BCD: Stunde, Minute, Sekunde, Frame — wobei das Frame-Byte in Bits 7–6 die Framerate codiert (01 = 25 fps, 11 = 29.97 fps) und in Bits 5–0 die Framenummer (BCD). Konvertierung (vertraglich):

```
ms = (bcd(h)*3600 + bcd(m)*60 + bcd(s)) * 1000
   + round(bcd(f) * 1000 / fps)        # fps = 25 oder 29.97 aus Bits 7-6
```

29.97 wird exakt als 30000/1001 gerechnet, Rundung kaufmännisch — fixiert, damit Signaturen reproduzierbar sind. Inkonsistente Framerate-Bits innerhalb einer PGC (Zellen mit 25, PGC-Zeit mit 29.97) sind Anomalie `mixed_framerate_flags`; es gilt die Mehrheits-Framerate der Zellen. Die PGC-`playback_time` wird gegen die Zellsummen validiert: Abweichung > 1 s ⇒ Anomalie `pgc_time_mismatch`, es gilt die **Zellsumme** (die PGC-Angabe lügt öfter, insbesondere bei Angle-PGCs, wo Authoring-Tools den Block mehrfach zählen).

Die Framerate je Playlist (`"25"` / `"29.97"`) ist Mapping-relevant: 25 fps ⇒ PAL-DVD ⇒ der 4-%-Speedup-Verdacht des Mappers ist aktiv ([mapping-algorithm.md](mapping-algorithm.md), Normalisierung N-02). NTSC-DVDs (29.97) laufen in Echtzeit; für sie ist der Speedup-Prior aus.

## Navigations-Mechanik: was der Analyzer bewusst ignoriert

Die DVD-VM (pre/post/cell/button commands, GPRM/SPRM-Register, Parental-Branching, Sprachen-Weichen) macht DVD-Navigation Turing-nah. Der Analyzer **interpretiert keine Befehle** — mit zwei gezielten Ausnahmen: statische `LinkPGCN`/`JumpTT`-Post-Commands (⇒ `chained_to`, siehe oben) und die Erkennung von **First-Play-PGC → Titelmenü**-Ketten (Standard-Muster, bestätigt `has_dvd_menu`). Alles Weitere — Zufalls-Wiedergabe über Register, Parental-Alternativschnitte, Easter-Egg-Sprünge — bleibt uninterpretiert; die betroffenen PGCs sind trotzdem vollständig als Struktur erfasst und werden über Laufzeit-/Gruppen-Heuristiken klassifiziert. Das ist eine bewusste Kapitulation vor der VM-Komplexität mit begrenztem Schaden: Die Playback-Übersetzung arbeitet positionsbasiert auf der tatsächlich gespielten PGC (der Player weiß, wo er ist — die Engine muss es nicht vorhersagen).

**Parental-Management-Discs** (mehrere PGCs desselben Titels mit Zellen-Teilmengen je Freigabestufe) erscheinen dadurch als Playlist-Gruppe mit hoher Clip-Überlappung — dieselbe Mechanik wie Seamless-Branching-Fassungsgruppen bei BD, und sie wird identisch behandelt (Fassungsgruppe, ein Mapping-Ziel).

## Multi-Angle und Interleaving

Angle-Blocks (Zellen-Kategorie) werden als ein PlayItem mit `angle_count` modelliert (Tabelle oben). Interleaved-Flags sind Anzeige-/Diagnose-Attribute am Clip. Positions-Semantik wie bei BD: Die PGC-Zeitachse ist winkelunabhängig linear — `position_ms` eines Players ist eindeutig. Seltene Ausnahme: **Seamless-Angle-Informationen mit abweichenden Winkellängen** (spezifikationswidrig, aber existent) — der Analyzer meldet `angle_length_mismatch` und verwendet die Länge von Winkel 1; der Positionsfehler bei Wiedergabe anderer Winkel bleibt unter der Watched-Schwellen-Toleranz.

## Menüs

VMG- und VTS-Menüs werden als `disc_menus`-Zeilen erfasst (`dvd_vmg` einmal, `dvd_vts` je VTS mit Menü-PGCs), mit Sprachen-Einheiten als `notes`-Detail. `has_dvd_menu = true`, sobald irgendeine Menü-PGC mit Video existiert (reine Dummy-Menü-PGCs ohne VOB — bei Billig-Authoring verbreitet — zählen nicht; Anomalie `stub_menu`). Mehr Menü-Modellierung braucht die Engine nicht: Menü-Playback ist Player-Sache, und DVD-Menü-PGCs sind durch die getrennte Tabellenherkunft (VTSM_PGCI_UT) strukturell sauber von Titel-PGCs getrennt — anders als bei BD, wo Menü-Loops als normale Playlists auftreten und heuristisch erkannt werden müssen. Deshalb ist die Junk-Quote der DVD-Klassifikation systematisch niedriger.

## CSS-Verschlüsselung

CSS verschlüsselt VOB-Sektoren, **nie IFO-Dateien** — Strukturanalyse funktioniert auf CSS-verschlüsselten Images uneingeschränkt. Erkennung: Copyright-Management-Flags in den VOB-Sektorköpfen der ersten Zellen (Stichprobe) ⇒ `css_present: true|false|null`. Verhalten identisch zum AACS-Fall der BD-Referenz: Hinweis fürs Player-Routing, keinerlei Entschlüsselung durch MediaForge, keine Schlüssel-Distribution. libdvdread wird bewusst **ohne** libdvdcss betrieben (Structure-only-Zugriff braucht es nicht; die rechtliche Grauzone wandert vollständig zum Player). RCE (Region Code Enhancement) und strukturbasierte Kopierschutz-Tricks (ARccOS, RipGuard) erscheinen als Anomalien — dazu der eigene Abschnitt.

## Struktur-Obfuskation bei DVD: ARccOS & Co.

Sony ARccOS, Macrovision RipGuard und Verwandte beschädigen die DVD-Struktur gezielt so, dass Player (die der Navigation folgen) funktionieren, Ripper (die linear lesen) aber scheitern: absichtlich defekte Sektoren in nie angespielten Zellen, PGCs mit Zellen, die auf ungültige VOB-Bereiche zeigen, überlange Fake-PGCs mit Tausenden Programmen, verschachtelte Verweise. Rips solcher Discs (der Ripper hat die Tricks bereits durchbrochen) enthalten die strukturellen Narben oft noch. Analyzer-Verhalten (normativ):

* PGCs mit Zellen außerhalb der Cell Address Table ⇒ Zelle als `dangling_cell` verworfen, PGC als `structurally_broken` ⇒ Klassifikation `junk`.
* PGCs mit > 99 Programmen oder > 4 h Laufzeit bei < 1 GiB VOB-Substanz ⇒ `junk` mit Obfuskations-Evidenz (Laufzeit ist erlogen).
* Mehr als 30 Titel-PGCs, von denen < 20 % plausible Substanz haben ⇒ Disc-weiter Obfuskations-Verdacht (`obfuscation_suspected: true`), Klassifikator wird konservativ (Auto-Mapping aus, wie beim BD-Pendant).

Der entscheidende Unterschied zu BD-Obfuskation: DVD-Fakes sind meist *strukturell defekt* (zeigen ins Leere), BD-Fakes meist *strukturell valide Duplikate* — deshalb dominiert bei DVD die Kaputtheits-Prüfung, bei BD das Duplikat-Falten.

## Kanonisierung und Struktur-Signatur (DVD-Abschnitt, normativ)

Gleiches Verfahren wie BD (BLAKE3 über kanonische Zeilen), DVD-spezifische Serialisierung:

1. Playlists aufsteigend nach `playlist_ref` (`VTS01/PGC01` < `VTS01/PGC02` < `VTS02/PGC01`; zweistellige Nummern).
2. Playlist-Zeile: `P|{ref}|{duration_ms}|sequential` (DVD kennt kein playback_type-Äquivalent; konstant für Format-Einheitlichkeit).
3. Zellen-Zeile: `I|{clip_ref}|0|{duration_ms}|{seamless?6:1}|{angle_count}`.
4. Marken-Zeile: `M|{position_ms}` (Programm-Marken).
5. Verkettung, BLAKE3-256, hex.

Menü-PGCs sind **nicht** signaturbestandteil (analog BD: Menüs identifizieren nicht das AV-Strukturbild; außerdem differieren Menü-Sprachvarianten zwischen Regionsausgaben bei identischem Titelinhalt). `structurally_broken`-PGCs werden mit ihrer normalisierten (verworfene Zellen entfernten) Form signiert — zwei Rips derselben ARccOS-Disc mit unterschiedlich vielen intakten Fake-Resten erhalten so meist trotzdem dieselbe Signatur; wo nicht, greift die Mapping-Vererbung eben nicht (sicherer Default: keine falsche Identitätsbehauptung).

## Anomalie-Katalog (DVD)

Ergänzend zu den formatneutralen Codes der BD-Referenz (`used_backup`, `nonstandard_case` gelten identisch):

| Code | Bedeutung | Normalisierung |
|---|---|---|
| `label_mismatch` | UDF- und ISO-Label differieren | UDF gewinnt |
| `mixed_framerate_flags` | inkonsistente fps-Bits in PGC | Mehrheits-Framerate der Zellen |
| `pgc_time_mismatch` | PGC-Zeit ≠ Zellsumme ± 1 s | Zellsumme gilt |
| `dangling_cell` | Zelle zeigt auf ungültigen VOB-Bereich | Zelle verworfen; PGC ggf. `structurally_broken` |
| `stub_menu` | Menü-PGC ohne Video-Substanz | zählt nicht für `has_dvd_menu` |
| `angle_length_mismatch` | Winkel ungleicher Länge im Block | Winkel-1-Länge gilt |
| `orphan_pgc` | Titel-PGC ohne TT_SRPT-Erreichbarkeit | erfasst mit `orphan`-Evidenz |
| `zero_cell` | Zelle mit Dauer 0 | verworfen |
| `bup_divergent` | IFO und BUP inhaltlich verschieden | IFO gewinnt, sofern parsebar (sonst BUP + `used_backup`) |
| `audio_ts_content` | AUDIO_TS mit Inhalt | ignoriert, Hinweis (DVD-Audio nicht unterstützt) |
| `vob_gap` | VTS_nn_k.VOB-Segment fehlt in der Mitte | Größen unvollständig; Struktur unbeeinflusst |
| `oversized_vts` | > 99 PGCs in einem VTS | Obfuskations-Prüfung (siehe oben) |

## disc-analysis/v1: DVD-Abschnitt des Schemas

Abweichungen und Ergänzungen gegenüber dem BD-Abschnitt (gemeinsame Felder — `schema`, `analyzer_version`, `label`, `anomalies`, `timing` etc. — identisch):

| Pfad | Typ | Inhalt |
|---|---|---|
| **`disc_kind`** | enum | konstant `dvd` |
| **`source_form`** | enum | `iso`, `video_ts_folder` |
| `fs_layer` | enum | `udf`, `iso9660` |
| `native_disc_id` | string | `"{provider_id}:{pos_code}"`, sofern vorhanden |
| `css_present` | bool/null | CSS-Stichprobe |
| `obfuscation_suspected` | bool | ARccOS-artige Muster erkannt |
| **`menus`** | object | `{hdmv: null, bdj: null, dvd: bool}` |
| `vmg` | object | siehe VMG-Extraktion |
| **`clips[]`** | array | Zell-basierte Clips: `ref`, `duration_ms`, `size_bytes` (anteilig aus Sektorspannen), `video` (Codec konstant `mpeg2`, Auflösung aus VTS-Attributen: 720/704/352 × 576/480, `aspect`, `framerate`), `audio[]` (aus VTS_ATRT: `ac3`, `mpeg1`, `mpeg2ext`, `lpcm`, `dts`; Sprache aus Stream-Attributen), `angles` |
| **`playlists[]`** | array | PGC-basiert, siehe VTS-Extraktion; zusätzlich `title_hint`, `chained_to`, `programs` |
| `title_playlist_hints` | object | Titel → PGC, bei DVD fast vollständig |

DVD-Clips haben gegenüber BD zwei Besonderheiten: `size_bytes` ist eine **Sektorspannen-Schätzung** (First/Last-VOBU-Adressen × 2048; Interleaved-Blocks machen sie ungenau — Feld nullable, Genauigkeitsklasse in `size_estimated: true`), und die Auflösung stammt aus VTS-weiten Attributen, nicht aus dem Stream selbst (alle Zellen eines VTS teilen die Video-Attribute — DVD erlaubt keinen Auflösungswechsel innerhalb eines Title Sets).

## Nicht modelliert (bewusst)

**DVD-VM-Befehle** über die zwei genannten statischen Muster hinaus — Begründung im Navigations-Abschnitt. **NAV-Packs (PCI/DSI)** in den VOBs — Realzeit-Navigationsdaten für Player; die IFO-Zeittabellen genügen der Engine (Positions-Reporting kommt in Millisekunden vom Player, nicht in Sektoren). **Time Maps (VTS_TMAPTI)** — Suchtabellen, Player-Domäne (Parallele zur EP-Map bei BD). **SubPicture-Inhalte** — Untertitel-Bitmaps werden nie dekodiert; Sprachliste genügt. **Karaoke-Modi, Line21/Closed-Caption-Flags** — Nischen-Attribute ohne Modell-Nutzen. **DVD-Audio (AUDIO_TS)** — eigenes Format außerhalb des Disc-Engine-Auftrags; würde als eigener Kandidaten-Typ des Classifiers modelliert, wenn je gewünscht (offener Punkt im Modulkapitel, bewusst nicht gezogen). **Enhanced/ECMA-DVDs mit PC-Partitionen** — der Video-Teil wird normal analysiert, PC-Inhalte ignoriert.

## Praktische Unterschiede BD ↔ DVD in der Engine (Zusammenfassung)

| Aspekt | BD | DVD |
|---|---|---|
| Playlist-Quelle | MPLS-Dateien | Titel-PGCs aus IFO |
| Schnittfenster | In/Out je PlayItem | keins (Zellen ganz) |
| Kapitelquelle | EntryMarks | Programm-Grenzen |
| Menü-Erkennung | heuristisch (Menü-Loops sind normale Playlists) | strukturell (getrennte Menü-Tabellen) |
| Obfuskations-Muster | valide Duplikat-Playlists | defekte Fake-Strukturen |
| Zeitpräzision | 45-kHz-Ticks | BCD-Frames (25/29.97) |
| PAL-Speedup-Prior | nur bei 25-fps-Streams | bei 25 fps (Standardfall PAL) |
| `title_only`-Reporting | lückenhafte Hints | fast vollständige Hints |
| Verschlüsselung Strukturzugriff | unbeeinträchtigt (AACS) | unbeeinträchtigt (CSS) |

Diese Tabelle ist die Kurzform dessen, was Klassifikator ([classification-rules.md](classification-rules.md)) und Mapper ([mapping-algorithm.md](mapping-algorithm.md)) formatspezifisch parametrisieren; die Algorithmen selbst sind formatneutral über dem generischen Modell definiert.
