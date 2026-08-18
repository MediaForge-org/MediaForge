# Hörbücher – Editionen, offizielle Kapitel, CUE/Sidecars und Storage Strategy

Priorität: P0 Datenmodell / P1 Chapter Discovery & Storage UI

Referenzen:

- `37_audiobook_single_file_chapter_verification.png`
- `38_audiobook_storage_strategy.png`

## 1. Work != Edition != File

```text
Audiobook Work
├── German Unabridged – Narrator A
│   ├── Chapter Model
│   └── Audio Files
├── German Abridged – Narrator B
└── English Unabridged – Narrator C
```

Kapitel gehören zur **Edition**, nicht zu einer zufälligen Dateiaufteilung.

## 2. Eine große Datei

Wenn MediaForge nur eine große Audiodatei findet:

1. technische Analyse;
2. Edition anhand von Titel, Autor, Sprache, Narrator, Publisher, ISBN/ASIN/Source IDs, Laufzeit bestimmen;
3. verlässliche/official Chapter Sources suchen;
4. Kapitelstruktur und ggf. Zeiten verifizieren;
5. bei eindeutiger Zuordnung Chapter Entities erzeugen;
6. UI zeigt Kapitel sofort wie bei nativem M4B.

## 3. Chapter Verification

Quellenstatus:

```text
official
aligned
ai_detected
manual
unresolved
```

`official` darf nur verwendet werden, wenn die passende Edition eindeutig belegt ist.

Wenn nur Kapiteldauern vorliegen, können Startzeiten deterministisch aufsummiert werden, sofern Summe/Edition eindeutig passen.

## 4. Audioanalyse als Fallback

Wenn offizielle Kapitelnamen vorhanden sind, aber keine Zeitpunkte:

- Silence/transition detection;
- Chapter callouts per speech transcript;
- Musik/Jingle-Grenzen;
- Sprecher-/Atmosphärewechsel;
- Abgleich gegen Kapitelanzahl und Gesamtlaufzeit.

Unsichere Zuordnung -> Review.

## 5. Canonical Storage

PostgreSQL speichert:

```text
audiobook_chapters
├── edition_id
├── number
├── title
├── start_ms
├── end_ms
├── source_fact_id
└── verification_state
```

## 6. Sidecars

Optionale portable Outputs:

- `.cue`;
- `.chapters.json`;
- später native M4B chapter metadata in einer neuen/ausdrücklich bearbeiteten Edition.

MediaForge DB bleibt kanonisch, weil CUE keine vollständige Provenienz/Verifikation ausdrücken kann.

## 7. Storage Strategy – User Choice

Nach verifizierter Kapitelerkennung wählt der Benutzer:

### A. Only in MediaForge

- Original bleibt;
- Kapitel existieren logisch in DB;
- kleinster Footprint.

### B. MediaForge + CUE/Sidecar

- Original bleibt;
- portable Sidecar-Dateien werden erzeugt.

### C. Split into Chapter Files

- separate Dateien je Kapitel;
- Naming Preview;
- Zielordner;
- Format/Copy/Reencode-Regeln;
- Original Handling explizit auswählbar.

## 8. Original Handling

Optionen:

```text
Keep Original        # Default
Archive Original
Delete after verified success
```

Löschen ist nie Default und erfordert explizite Bestätigung.

## 9. Lossless / No-quality-loss Preference

Wenn exaktes Splitten ohne Re-Encoding möglich ist, wird Stream Copy/lossless split bevorzugt.

Wenn ein verlustbehaftetes Re-Encoding nötig wäre, zeigt MediaForge die Konsequenz vor Ausführung. Ein stiller Qualitätsverlust ist verboten.

## 10. Naming

Beispiel:

```text
{chapter:02} - {chapter_title}
{book_title} - {chapter:02} - {chapter_title}
```

Preview wird vor Dateischreiboperationen angezeigt.

## 11. Multiple Files, Same Chapters

Kapitel und Files bleiben unabhängig. 4 Files können 22 Kapitel abbilden; 22 Files können 22 Kapitel abbilden; 1 File kann 22 Kapitel abbilden.

## 12. Transcript & Semantic Search (P1/P2)

Optionaler vollständiger Transcript Index ermöglicht:

```text
"wann erklärt Gandalf den Ring?"
-> Chapter 3, 01:18:42
```

Treffer springt direkt zur Audioposition.

## 13. Bookmarks/Notes/Highlights

User Notes speichern genaue Audiopositionen und optional Transcript-Ausschnitt.

## 14. Hörspiele / Full Cast (P2)

Später können Speaker/Character/Music/SFX-Timelines erzeugt werden. Automatische Charakterzuordnung ist nur bei verlässlicher Evidence erlaubt.

## 15. Persistent metadata and Books relationship

Once Audiobookshelf/provider metadata is captured, MediaForge stores it as provenance-bearing source facts in PostgreSQL. Renaming/moving the audio file or an ABS rescan must not erase it.

Audiobooks are audio editions of a literary Work; textual BookEditions are first-class related media with their own reader/progress model.

See `docs/MediaForge/modules/books-ebooks-and-persistent-metadata.md`.
