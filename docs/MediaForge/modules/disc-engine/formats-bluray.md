# Formatreferenz Blu-ray / UHD Blu-ray (BDMV)

Vertiefung zu [modules/disc-engine.md](../disc-engine.md), Abschnitt „Disc-Format-Grundlagen". Diese Referenz ist normativ für zwei Verträge: (1) **was** der media-tools-Analyzer aus einer BDMV-Struktur extrahiert (`disc-analysis/v1`), und (2) **wie** die Engine die extrahierten Daten interpretiert (Kanonisierung, Signatur, Anomalie-Normalisierung). Sie beschreibt die Formate exakt so weit, wie die Engine sie modelliert — sie ist keine vollständige BD-Spezifikations-Zusammenfassung. Wo die offizielle Spezifikation (BD-ROM Part 3: Audio Visual Basic Specifications) mehr definiert als hier steht, ist das Weggelassene bewusst außerhalb des Modells (Abschnitt „Nicht modelliert").

## Geltungsbereich und Lesehinweise

Der Analyzer läuft im media-tools-Dienst (Python/C-Bindings um libbluray); PHP sieht ausschließlich das versionierte JSON. Deshalb gliedert sich jeder Formatabschnitt in drei Ebenen: **Binärformat** (was auf der Disc steht — Hintergrundwissen für Analyzer-Entwickler und Debugging), **Extraktion** (welche Felder der Analyzer liest und wie er sie normalisiert) und **Modell** (worauf die Engine sie abbildet). Nur Extraktion und Modell sind vertraglich; das Binärformat ist beschreibend und referenziert die Feldnamen der öffentlichen Spezifikationsliteratur, damit Analyzer-Code und Hexdump-Debugging zusammenfinden.

## Dateisystem-Ebene: UDF

Blu-rays verwenden UDF 2.50 (BD-ROM) bzw. UDF 2.60 (BD-R/RE mit POW); UHD-BDs ebenfalls UDF 2.50. Anders als DVDs enthalten sie **keine** ISO-9660-Bridge — ein naives Tool, das nur ISO-9660 liest, sieht ein leeres Dateisystem. Konsequenzen für die Engine:

* **ISO-Zugriff ohne Mount:** libbluray bringt einen eigenen UDF-Parser mit (`bd_open()` akzeptiert ISO-Pfade direkt seit 1.0). Der Analyzer mountet niemals (kein Loop-Device, keine Privilegien, keine Mount-Leichen bei Crashes); er öffnet das Image als Datei und liest UDF in User-Space. Für Ordner-Formen (`BDMV/`-Verzeichnis direkt im Dateisystem) entfällt die UDF-Schicht.
* **Große Dateien:** M2TS-Dateien überschreiten regelmäßig 4 GiB (UDF hat kein FAT32-Limit). Der Analyzer braucht 64-Bit-Offsets durchgängig; `size_bytes` im Vertrag ist deshalb als 64-Bit-Ganzzahl definiert.
* **Volume-Label:** Das UDF Logical Volume Identifier-Feld liefert `label` (z. B. `STAGE_3_DISC_2`). Es ist d-string-codiert (8- oder 16-Bit-Unicode mit Längenpräfix); der Analyzer dekodiert nach UTF-8 und trimmt Padding. Das Label ist ein Mapping-Kontext-Signal schwächster Priorität (siehe [mapping-algorithm.md](mapping-algorithm.md)) — es ist herstellerabhängig zwischen sprechend (`GAME_OF_THRONES_S3_D2`) und nutzlos (`LOGICAL_VOLUME_ID`, `UNTITLED`, Leerstring).
* **Groß-/Kleinschreibung:** UDF ist case-sensitiv, die BD-Spezifikation schreibt Großbuchstaben-Pfade vor (`BDMV/PLAYLIST/00003.mpls`). Reale gebrannte BD-Rs von Consumer-Software weichen gelegentlich ab; der Analyzer sucht case-insensitiv und meldet abweichende Schreibung als Anomalie `nonstandard_case` (siehe Anomalie-Katalog).

## Verzeichnisstruktur: vollständige Referenz

Ergänzend zur Übersichtstabelle des Modulkapitels die vollständige Struktur mit Analyzer-Verhalten je Eintrag:

| Pfad | Pflicht | Analyzer-Verhalten |
|---|---|---|
| `BDMV/index.bdmv` | ja | vollständig geparst (Titel, First Play, Top Menu) |
| `BDMV/MovieObject.bdmv` | ja | Existenz + Objektzahl; Navigationsprogramme werden **nicht** interpretiert |
| `BDMV/PLAYLIST/xxxxx.mpls` | ≥ 1 | vollständig geparst — Kernextraktion |
| `BDMV/CLIPINF/xxxxx.clpi` | ≥ 1 | vollständig geparst — Kernextraktion |
| `BDMV/STREAM/xxxxx.m2ts` | ≥ 1 | nur `stat()`: Existenz, Größe; optional ffprobe-Stichprobe |
| `BDMV/STREAM/SSIF/xxxxx.ssif` | nein | Existenz ⇒ 3D-Disc-Hinweis (Stereoscopic Interleaved File) |
| `BDMV/AUXDATA/` | nein | ignoriert (Sound-Daten für Menüs, Fonts) |
| `BDMV/META/DL/bdmt_xxx.xml` | nein | geparst, sofern vorhanden: Disc-Library-Metadaten (Titelname, Thumbnails) |
| `BDMV/BDJO/*.bdjo` | nein | Existenz + Anzahl ⇒ `has_bdj_menu` |
| `BDMV/JAR/*.jar` | nein | Existenz; Inhalte werden **nie** entpackt oder ausgeführt |
| `BDMV/BACKUP/` | nein | vollständig ignoriert; Ausnahme: Reparaturpfad bei korrupten Primärdateien (Anomalie `used_backup`) |
| `CERTIFICATE/` | ja | ignoriert (BD-J-Signaturen, App-Discovery) |
| `AACS/` | bei AACS | Existenz ⇒ `is_encrypted`-Verdacht; `mcmf.xml`/Unit_Key_RO nicht gelesen |
| `MAKEMKV/`, `ANY!/` u. ä. | nein | Ripper-Artefakte; erfasst als `ripper_artifacts` (informativ) |

**Meta-XML (`bdmt_ger.xml` u. a.):** Wenn vorhanden, extrahiert der Analyzer `di:name` (Disc-Titel je Sprache) und Thumbnail-Referenzen. Der Titel ist ein weiteres schwaches Kontextsignal fürs Mapping und wird als `meta_title` im JSON geliefert. Die Thumbnails werden referenziert, aber nicht kopiert (Originale bleiben unangetastet, [ADR-0005](../../adr/0005-immutable-originals.md)); das Enrichment-Modul darf sie später als Cover-Kandidaten anbieten.

## index.bdmv

### Binärformat

Big-Endian-Binärformat mit Magic `INDX`, Versionsstring `0100`/`0200`/`0300` (BDMV v1/v2/v3 — v3 ⇒ UHD). Danach die Indexes-Tabelle: **First Playback** (was beim Einlegen startet), **Top Menu** (was die Menü-Taste startet), dann n **Titles**. Jeder Eintrag ist entweder ein HDMV-Objekt (verweist auf MovieObject-Nummer) oder ein BD-J-Objekt (verweist auf BDJO-Datei); zusätzlich das Access-Flag (Title Search erlaubt/verboten).

### Extraktion

```json
"index": {
  "bdmv_version": 3,
  "first_play": {"kind": "hdmv", "ref": 0},
  "top_menu": {"kind": "bdj", "ref": "00001"},
  "titles": [
    {"number": 1, "kind": "hdmv", "ref": 2, "access": "permitted"},
    {"number": 2, "kind": "bdj", "ref": "00002", "access": "prohibited"}
  ]
}
```

### Modell

`bdmv_version = 3` setzt `disc_kind='uhd_bluray'` (zusammen mit HEVC-Evidenz, siehe UHD-Abschnitt — die Version allein genügt, HEVC bestätigt). `first_play.kind = 'bdj'` oder `top_menu.kind = 'bdj'` ⇒ `has_bdj_menu = true`; existieren MovieObjects mit Menü-Charakteristik ⇒ `has_hdmv_menu = true`. Die Titles-Tabelle wird als Diagnose-Information aufbewahrt, aber **nicht** als Playlist-Ersatz verwendet: Titel sind Navigationseinheiten (ein Titel kann je nach Player-Zustand verschiedene Playlists starten), Playlists sind die stabile Struktureinheit. Player, die nur Titelnummern melden (`title_only`-Reporting), werden über eine Titel→Playlist-Wahrscheinlichkeitstabelle übersetzt, die der Analyzer aus den MovieObject-Sprungzielen ableitet, sofern eindeutig (`title_playlist_hints` im JSON; nicht eindeutige Titel liefern keinen Hint — lieber kein Mapping als ein geratenes, Architekturregel 11).

## MPLS (MoviePLayliSt) — Kernextraktion

### Binärformat

Magic `MPLS`, Version wie index.bdmv. Drei Hauptblöcke über Offset-Zeiger: **AppInfoPlayList**, **PlayList**, **PlayListMark**; optional **ExtensionData** (u. a. für Stereoscopic/UHD-Erweiterungen).

**AppInfoPlayList** enthält `playback_type` (1 = sequentiell, 2 = random, 3 = shuffle) und `playback_count` (für Random/Shuffle), dazu die UO-Mask (User-Operation-Verbote: Skip/Search/Pause-Sperren). Random/Shuffle-Playlists sind praktisch immer Menü-Hintergründe oder Jukebox-Konstrukte — der Klassifikator wertet `playback_type ≠ 1` als starkes Junk-Signal ([classification-rules.md](classification-rules.md), Regel R-03).

**PlayList** enthält `number_of_play_items` und `number_of_sub_paths`, gefolgt von den PlayItems. Jedes PlayItem trägt: `clip_information_file_name` (5-stellige CLPI-Referenz), `clip_codec_identifier` (immer `M2TS`), `is_multi_angle`, `connection_condition` (1 = nicht nahtlos, 5 = nahtlos mit Clean Break, 6 = nahtlos), `ref_to_STC_id`, `in_time`/`out_time` (32-Bit, **45-kHz-Ticks**), UO-Mask, `still_mode`, bei Multi-Angle die Angle-Liste (weitere Clip-Referenzen), und die **STN-Tabelle** (Stream Number Table: welche Video-/Audio-/PG-/IG-Streams in diesem PlayItem aktiv sein dürfen, mit PID-Referenzen und Attributen).

**SubPaths** definieren asynchron laufende Zusatzpfade (Typ 2/3: Audio-Browsable Slideshow, 4: Interactive Graphics, 5/6: Out-of-mux Audio/PiP, 8/9/10: UHD-Erweiterungen). Für die Engine relevant: SubPath Typ 8 (Dolby-Vision-Enhancement-Layer bei Dual-Layer-DV-Discs) wird als `has_dv_enhancement_layer` erfasst.

**PlayListMark** ist die flache Markenliste: jede Marke hat `mark_type` (1 = EntryMark ⇒ Kapitel; 2 = LinkPoint ⇒ Sprungziel für Navigation, kein Kapitel), `ref_to_play_item_id`, `mark_time_stamp` (45-kHz-Ticks auf der Zeitachse des referenzierten PlayItems), optional `entry_es_pid` und `duration`.

### Zeitmodell (normativ)

Alle MPLS/CLPI-Zeiten sind 45-kHz-Ticks (1/45000 s). Der Analyzer konvertiert **verlustbehaftet abrundend** nach Millisekunden: `ms = ticks * 1000 // 45000` (Integer-Division). Die Konvertierung ist im Vertrag fixiert, damit Signaturen reproduzierbar sind: Zwei Analyzer-Versionen, die dieselbe Disc lesen, müssen bit-identische `duration_ms` liefern. Die PlayItem-Dauer ist `out_time − in_time`; die Playlist-Dauer die Summe der PlayItem-Dauern (Angles zählen einfach — Winkel verlängern nichts). Markenpositionen werden von PlayItem-relativer Zeit auf die Playlist-Zeitachse kumuliert: `mark_ms = Σ dauer(playitems < ref_id) + (mark_time_stamp − in_time(ref_id)) / 45`. Marken vor `in_time` oder hinter `out_time` ihres PlayItems sind Anomalien (`mark_out_of_range`) und werden auf die PlayItem-Grenze geklemmt.

### Extraktion

```json
"playlists": [{
  "ref": "00003",
  "playback_type": "sequential",
  "duration_ms": 2592000,
  "uo_mask_significant": false,
  "items": [
    {"seq": 1, "clip_ref": "00012", "stc_id": 0, "in_ms": 0, "out_ms": 2581040,
     "connection": "not_seamless", "seamless": false, "angles": 1,
     "still_mode": "none",
     "streams": {"video": [{"pid": 4113, "codec": "h264"}],
                 "audio": [{"pid": 4352, "codec": "dts_hd_ma", "lang": "deu"},
                           {"pid": 4353, "codec": "ac3", "lang": "eng"}],
                 "pg":    [{"pid": 4608, "lang": "deu"}]}}
  ],
  "chapters_ms": [0, 122000, 601000, 1489000, 2405000],
  "link_points_ms": [],
  "sub_paths": [{"type": 5, "purpose": "out_of_mux_audio"}]
}]
```

`chapters_ms` enthält ausschließlich EntryMarks (Typ 1), aufsteigend dedupliziert (zwei Marken < 500 ms auseinander werden gefaltet — reale Discs enthalten Doppelmarken durch Authoring-Fehler; Anomalie `duplicate_marks`). LinkPoints werden getrennt geliefert und fließen **nicht** in `chapter_count` des Datenmodells ein.

### Modell

Ein MPLS ⇒ eine `disc_playlists`-Zeile (`playlist_ref` = fünfstellige Nummer als Text, führende Nullen erhalten). PlayItems ⇒ `disc_playlist_items` mit `seq` 1-basiert in Dateireihenfolge. Marken ⇒ `disc_playlist_marks` (nur EntryMarks). Die STN-Tabelle wird nicht relational modelliert; die aggregierten Stream-Eigenschaften leben am Clip (unten). Die UO-Mask fließt als `uo_mask_significant` (Boolean: „verbietet die Playlist Suchen/Springen?") in die Klassifikations-Evidence — Warnhinweis-Playlists mit gesperrten User-Operations sind ein Junk-Signal.

## CLPI (CLiPInfo) — Kernextraktion

### Binärformat

Magic `HDMV` + Typkennung, Blöcke: **ClipInfo** (TS-Typ, TS-Rate, Anzahl Source-Pakete), **SequenceInfo** (ATC/STC-Sequenzen: Diskontinuitäten der Zeitbasis innerhalb des Clips), **ProgramInfo** (Programme mit Stream-Einträgen: PID, Codec, Auflösung/Framerate bei Video, Sprache/Kanalzahl/Abtastrate bei Audio), **CPI** (Characteristic Point Information — die EP-Map: Einsprungpunkte PTS→Paketnummer für Suchoperationen), **ClipMark** (praktisch ungenutzt), optional **ExtensionData** (u. a. HDR-Metadaten-Deskriptoren bei UHD).

### Extraktion

Der Analyzer liest ClipInfo (Plausibilisierung der M2TS-Größe: `num_source_packets × 192 ≈ dateigröße`; Abweichung > 1 % ⇒ Anomalie `stream_size_mismatch`, deutet auf beschnittene Rips), SequenceInfo (Anzahl STC-Sequenzen — mehr als eine ⇒ Zeitbasis-Sprünge im Clip, relevant für Positionspräzision), ProgramInfo (vollständige Stream-Tabelle) und die Präsentationszeiten (`presentation_start_time`/`end_time` aus SequenceInfo ⇒ Clip-Dauer, gleiche 45-kHz-Konvertierung). Die EP-Map wird **nicht** exportiert (nur Player brauchen sie); ihre bloße Existenz je Video-PID wird als `has_ep_map` geprüft (fehlende EP-Map ⇒ Anomalie, Disc sucht schlecht — reine Diagnose-Info).

Codec-Normalisierung (vertraglich fixierte Enum-Werte): Video `mpeg2, h264, vc1, hevc`; Audio `lpcm, ac3, ac3plus, truehd, dts, dts_hd_hra, dts_hd_ma, dra`; die BD-Stream-Typ-Bytes (0x02, 0x1B, 0xEA, 0x24; 0x80–0x86, 0xA1/A2) werden im Analyzer auf diese Enums gemappt. Sekundär-Audio/-Video (Stream-Typen 0xA1/A2/0x1B-secondary) wird mit `role: "secondary"` markiert (PiP-Kommentare u. ä. — nie Mapping-relevant).

```json
"clips": [{
  "ref": "00012",
  "duration_ms": 2581040,
  "size_bytes": 5813400000,
  "stc_sequences": 1,
  "video": {"codec": "h264", "width": 1920, "height": 1080,
            "framerate": "23.976", "aspect": "16:9", "hdr": "none"},
  "audio": [
    {"pid": 4352, "codec": "dts_hd_ma", "lang": "deu", "channels": 6, "sample_rate": 48000},
    {"pid": 4353, "codec": "ac3", "lang": "eng", "channels": 6, "sample_rate": 48000}
  ],
  "pg_languages": ["deu", "eng"],
  "angles": 1
}]
```

### Modell

Ein CLPI ⇒ eine `disc_clips`-Zeile. `video_codec`, `video_width`, `video_height`, `hdr_format` direkt aus der Extraktion; `audio_streams` als JSONB-Anzeige-Array (bewusst nicht relational — es wird nie fachlich gefiltert, nur angezeigt; legitimes Anzeige-JSONB nach Regel 8). Die Framerate wird als String geführt (`"23.976"`, `"24"`, `"25"`, `"29.97"`, `"50"`, `"59.94"`) — sie ist Evidenz für PAL/NTSC-Unterscheidung im Mapping (25-fps-BDs existieren bei europäischen TV-Serien; der PAL-Speedup-Verdacht des Mappers greift bei BDs nur, wenn die Framerate 25/50 ist).

## Seamless Branching im Detail

Seamless Branching realisiert mehrere Schnittfassungen über gemeinsame Clips: Die Kinofassung ist Playlist A mit Clips `[C1, C2, C4, C5]`, der Extended Cut Playlist B mit `[C1, C2, C3, C4', C5]` — C3 ist die zusätzliche Szene, C4' ein alternativer Anschluss. Erkennungsmerkmale in der Struktur: `connection_condition = 5/6` an den Nahtstellen, hohe Clip-Überlappung zwischen den Playlists, oft zusätzlich Multi-Angle-PlayItems (Winkel als Fassungs-Weiche innerhalb eines PlayItems).

Konsequenzen für die Engine (normativ):

1. **Fassungsgruppen-Erkennung:** Zwei Playlists gehören zur selben Fassungsgruppe, wenn die Jaccard-Ähnlichkeit ihrer Clip-Mengen ≥ 0.5 beträgt **und** die gemeinsamen Clips ≥ 80 % der kürzeren Playlist-Laufzeit abdecken. Die Gruppe wandert in die Klassifikations-Evidence (`branching_group`).
2. **Positions-Semantik:** Die Playlist-Zeitachse ist trotz Branching linear (PlayItems sind sequenziell); `position_ms` eines Players ist immer eindeutig einer Stelle der jeweiligen Playlist zuzuordnen. Branching erzeugt also **keine** Mehrdeutigkeit in der Playback-Übersetzung — nur unterschiedliche Playlists für unterschiedliche Fassungen.
3. **Mapping:** Alle Playlists einer Fassungsgruppe mappen auf dasselbe Katalog-Item (Modulkapitel, Edge Case „Seamless Branching"). Der Mapper schlägt das Mapping für die Repräsentantin vor und repliziert es auf Gruppenmitglieder mit `evidence.replicated_from`.

## Multi-Angle

`is_multi_angle`-PlayItems referenzieren k Clips (Winkel 1..k) mit identischer Dauer. Der Analyzer liefert alle Winkel-Clip-Referenzen; das Modell speichert `angle_count` am PlayItem und am Clip. Winkel sind für Laufzeit, Kapitel, Mapping und Playback-Übersetzung vollständig transparent (die Positionsachse ist winkelunabhängig). Einzige fachliche Relevanz: Discs mit Multi-Angle-Hauptfilm (Konzertfilme, manche Anime-Openings) zeigen im UI ein Winkel-Badge.

## UHD Blu-ray (BDMV v3)

Strukturell identisch zu BDMV v2; die Engine behandelt UHD als Attribut-Variante, nicht als eigenes Format. Unterschiede, die der Analyzer erfasst:

| Merkmal | Erfassung | Modell |
|---|---|---|
| BDMV-Version `0300` | index.bdmv | `disc_kind='uhd_bluray'` |
| HEVC Main 10 (Stream-Typ 0x24) | CLPI ProgramInfo | `video_codec='hevc'` |
| Auflösung 3840×2160 | CLPI | `video_width/height` |
| HDR10 (SMPTE ST 2086 Mastering-Metadaten) | CLPI ExtensionData | `hdr_format='hdr10'` |
| HDR10+ (ST 2094-40 SEI) | ffprobe-Stichprobe nötig | `hdr_format='hdr10plus'` (nur bei aktivierter Stichprobe, sonst `hdr10`) |
| Dolby Vision (Dual Layer, SubPath Typ 8) | MPLS SubPaths + CLPI | `hdr_format='dolby_vision'`, `has_dv_enhancement_layer` |
| Dolby Vision (Single Layer, Profil 5 existiert auf UHD-BD nicht) | — | n/a |
| HLG | CLPI ExtensionData | `hdr_format='hlg'` |
| 66/100-GB-Layouts (BDXL, dreischichtig) | nur Dateigrößen | keine Modell-Relevanz |
| Kein 3D auf UHD | — | `SSIF`-Prüfung entfällt bei v3 |

HDR-Priorität bei Mehrfach-Signalen (eine DV-Disc trägt immer auch HDR10-Basis): `dolby_vision > hdr10plus > hdr10 > hlg > none` — es wird das reichhaltigste Format gespeichert; die Basis-Kompatibilität ist impliziert. UHD-typische AACS-2.x-Verschlüsselung: siehe AACS-Abschnitt.

## 3D Blu-ray (MVC)

3D-BDs (BDMV v2 mit MVC-Erweiterung) speichern das Dependent-View-Video in SSIF-Interleaved-Dateien und erweitern MPLS/CLPI per ExtensionData. Der Analyzer erkennt 3D an `STREAM/SSIF/`-Existenz plus MVC-Deskriptoren und liefert `is_3d: true` pro betroffener Playlist. Das Modell führt 3D als Anzeige-Attribut in der Klassifikations-Evidence; Laufzeiten, Kapitel und Mapping arbeiten auf der Base-View und sind von 3D unberührt. Externe Player erhalten den 3D-Hinweis über die Player-Öffnungs-Referenz (Kodi kann MVC; die Fähigkeits-Matrix steht im [External-Player-Kapitel](../../connectors/external-player.md)).

## AACS und Verschlüsselung

Kommerzielle BDs sind AACS-verschlüsselt (UHD: AACS 2.x); Rips liegen praktisch immer **entschlüsselt** vor (der Ripper hat AACS bereits entfernt), aber die Engine muss den verschlüsselten Fall sauber erkennen statt kryptisch zu scheitern:

* **Navigationsdaten sind unverschlüsselt.** index.bdmv, MPLS, CLPI unterliegen nicht der AACS-Verschlüsselung — die Strukturanalyse funktioniert auch auf verschlüsselten Images vollständig. Nur M2TS-Inhalte sind verschlüsselt.
* **Erkennung:** `AACS/`-Verzeichnis mit `Unit_Key_RO.inf` vorhanden ⇒ `aacs_present: true`. Ob die Streams tatsächlich noch verschlüsselt sind, entscheidet die ffprobe-Stichprobe (verschlüsselte TS-Pakete ⇒ Decode-Fehler): `streams_encrypted: true|false|null` (null = Stichprobe deaktiviert).
* **Verhalten:** `streams_encrypted=true` setzt einen Disc-Hinweis „verschlüsseltes Image — Menü-Playback erfordert AACS-fähigen Player, Positions-Reporting unbeeinträchtigt". Struktur, Klassifikation, Mapping und Watch-State-Übersetzung laufen normal (sie brauchen nie Stream-Inhalte). Die Engine entschlüsselt **nichts** und bündelt keine AACS-Schlüssel — das ist Player-Territorium und rechtlich dessen Problem.
* **BD+ / Screen Pass:** BD+-Virtual-Machine-Reste (`BDSVM/`) werden als Existenz-Flag erfasst; keine weitere Behandlung. BD+-obfuskierte Discs zeigen häufig zusätzlich Struktur-Obfuskation (Fake-Playlists) — das Flag fließt als Prior in den Obfuskations-Verdacht des Klassifikators.

## Kanonisierung und Struktur-Signatur (normativ)

Die Struktur-Signatur (Modulkapitel, „Disc-Identität") ist BLAKE3 über eine kanonische Bytefolge. Die Kanonisierung ist hier vollständig definiert; jede Änderung ist ein Breaking Change der Signatur und erfordert einen Migrations-Rebuild (`mediaforge:rebuild-disc-signatures`, mit Mapping-Erhalt über die alte Signatur):

1. Playlists aufsteigend nach `playlist_ref` (Text-Sortierung; führende Nullen machen sie numerisch äquivalent).
2. Je Playlist die Zeile: `P|{ref}|{duration_ms}|{playback_type}`.
3. Je PlayItem (in seq-Reihenfolge): `I|{clip_ref}|{in_ms}|{out_ms}|{connection}|{angles}`.
4. Je EntryMark (aufsteigend): `M|{position_ms}`.
5. Zeilen mit `\n` verkettet, UTF-8, BLAKE3-256, hex-codiert.

**Bewusst ausgeschlossen** aus der Signatur: Volume-Label und Meta-XML (Ripper-abhängig), Clip-Dateigrößen (Padding-Differenzen zwischen Rippern), Stream-Attribute (identische Struktur, unterschiedliche Rip-Einstellungen), Menü-Objekte (BD-J-Jars differieren zwischen Regionsfassungen bei identischem AV-Inhalt — strittig, aber entschieden: die Signatur identifiziert das *AV-Strukturbild*, nicht das Menüerlebnis), SubPaths (PiP-Kommentare fehlen in manchen Rips). Eingeschlossen ist `playback_type`, weil er das Klassifikationsergebnis prägt. Konsequenz: Ein „movie-only"-Rip (MakeMKV-Backup ohne Menüs, aber mit allen Playlists) erhält dieselbe Signatur wie der Voll-Rip — gewollt, die Mappings sind übertragbar.

## Anomalie-Katalog

Reale Discs verletzen die Spezifikation regelmäßig. Der Analyzer normalisiert deterministisch und meldet jede Normalisierung als typisierte Anomalie im JSON (`anomalies: [{code, subject, detail}]`); die Engine speichert sie in der Klassifikations-Evidence. Normativer Katalog:

| Code | Bedeutung | Normalisierung |
|---|---|---|
| `dangling_clip_ref` | MPLS referenziert CLPI, das nicht existiert | PlayItem wird mit `duration=0` geführt und die Playlist als `structurally_broken` markiert ⇒ Klassifikation `junk` |
| `orphan_clip` | CLPI ohne referenzierende Playlist | Clip wird erfasst (Vollständigkeit), Marker `orphan` — häufig Obfuskations-Reste |
| `mark_out_of_range` | Kapitelmarke außerhalb ihres PlayItems | auf PlayItem-Grenze geklemmt |
| `duplicate_marks` | Marken < 500 ms Abstand | gefaltet auf die frühere |
| `zero_duration_item` | `out_time ≤ in_time` | PlayItem verworfen, Dauer 0 |
| `overlong_playlist` | Dauer > 24 h (32-Bit-Tick-Überlauf-Artefakte) | Playlist als `junk`, Verdacht Obfuskation |
| `stream_size_mismatch` | M2TS-Größe ≠ CLPI-Paketzahl ± 1 % | nur gemeldet (beschnittener Rip) |
| `missing_stream_file` | CLPI ohne M2TS | Clip `size_bytes=null`; Playlists mit fehlenden Streams werden gemeldet, aber normal klassifiziert (movie-only-Rips!) |
| `nonstandard_case` | Pfade nicht großgeschrieben | case-insensitive aufgelöst |
| `used_backup` | Primärdatei korrupt, BACKUP/ verwendet | Reparaturhinweis |
| `bdjo_without_jar` | BDJO referenziert fehlendes JAR | `has_bdj_menu` bleibt true, Menü-Start wird voraussichtlich scheitern ⇒ Player-Hinweis |
| `empty_playlist_dir` | keine einzige MPLS | Analyse schlägt fach­lich fehl (`analysis_status='failed'`) — keine BD-Struktur |

Die Unterscheidung „Anomalie mit Normalisierung" vs. „Fachfehler" ist scharf: Alles im Katalog außer `empty_playlist_dir` produziert ein analysierbares Ergebnis. Der Fachfehler-Pfad (Review-Task `disc_analysis_failed`) ist Discs vorbehalten, aus denen keine konsistente Struktur zu gewinnen ist.

## disc-analysis/v1: BD-Abschnitt des Schemas (vollständig)

Das Modulkapitel zeigt das Gerüst; hier die vollständige Feldliste für BDMV-Quellen. Pflichtfelder fett; alle anderen nullable. Unbekannte Zusatzfelder im JSON sind vom Konsumenten zu ignorieren (Forward-Kompatibilität; Versionssprung nur bei inkompatiblen Änderungen).

| Pfad | Typ | Inhalt |
|---|---|---|
| **`schema`** | string | konstant `disc-analysis/v1` |
| **`analyzer_version`** | string | SemVer des media-tools-Analyzers |
| **`disc_kind`** | enum | `bluray`, `uhd_bluray` (DVD siehe [formats-dvd.md](formats-dvd.md)) |
| **`source_form`** | enum | `iso`, `bdmv_folder` |
| `label` | string | UDF-Volume-Label |
| `meta_title` | string | Titel aus META/DL-XML, bevorzugte Sprache |
| `native_disc_id` | string | Organisations-/Disc-ID, sofern lesbar |
| `aacs_present` | bool | AACS-Verzeichnis vorhanden |
| `streams_encrypted` | bool/null | ffprobe-Stichproben-Ergebnis |
| `is_3d` | bool | MVC/SSIF vorhanden |
| **`menus`** | object | `{hdmv: bool, bdj: bool, dvd: null}` |
| `index` | object | siehe index.bdmv-Extraktion |
| `title_playlist_hints` | object | Titelnummer → Playlist-Ref, nur eindeutige |
| **`clips[]`** | array | siehe CLPI-Extraktion |
| **`playlists[]`** | array | siehe MPLS-Extraktion |
| `branching_groups[]` | array | Clip-Überlappungsgruppen `[{playlist_refs, jaccard}]` |
| `anomalies[]` | array | Anomalie-Katalog |
| `ripper_artifacts[]` | array | erkannte Fremdverzeichnisse |
| **`timing.analysis_ms`** | int | Analysedauer (Diagnose) |
| `timing.io_bytes_read` | int | gelesene Bytes (NAS-Last-Diagnose) |

Fixtures: Für jede im [Test-Katalog](test-catalog.md) geführte Fixture-Disc existiert das vollständige JSON unter `tests/fixtures/disc-analysis/`; das JSON-Schema selbst liegt als `schemas/disc-analysis.v1.json` im media-tools-Repo und wird in CI gegen alle Fixtures validiert.

## Nicht modelliert (bewusst)

Zur Abgrenzung, mit Begründung: **HDMV-Navigationsprogramme** (MovieObject-Bytecode) — die Engine spielt nicht ab; Titel-Hints genügen. **BD-J-Inhalte** — Java-Code wird nie inspiziert oder ausgeführt (Security: feindliche JARs; Nutzen: null, da Playback beim externen Player liegt). **EP-Maps** — Suchtabellen sind Player-Domäne. **PiP/SubPath-Inhalte** — Sekundärvideo ist nie Mapping-Ziel (kein eigenständiges Werk). **UO-Masken im Detail** — nur das aggregierte Signifikanz-Flag. **Virtual Packages / BD-Live** — Netzinhalte existieren im Archiv-Kontext nicht. **Region-Codes** — BDs tragen Region-Locks im Player-Code, nicht maschinenlesbar in der Struktur; irrelevant für Rips. Wer eines dieser Felder später braucht, erweitert `disc-analysis` additiv (v1-kompatibel) oder begründet v2 — der Vertrag ist der Änderungspunkt, nicht die PHP-Seite.
