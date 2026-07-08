# ADR-0008: Kapitelquellen-Hierarchie mit KI als nie-automatischer Untergrenze

Status: accepted · Bezug: [modules/audiobook-assembler.md](../modules/audiobook-assembler.md), Architekturregel 5

## Kontext

Für dasselbe Hörbuch existieren regelmäßig mehrere, einander widersprechende Kapitelstrukturen: offizielle Anbieterlisten, eingebettete Container-Kapitel, CUE-/Sidecar-Dateien, Trackgrenzen, KI-Ableitungen. Bestehende Werkzeuge wählen ad hoc (m4b-tool: Dateiname oder Stille; ABS: was eingebettet ist) und dokumentieren die Herkunft nicht.

## Entscheidung

Kapitelstrukturen werden als vollständige, unabhängige Chapter Sets mit Herkunft (`origin`), Offizialitäts-Flag und Rohdaten geführt; genau ein Set ist aktiv (partieller Unique-Index). Die Auswahl folgt einer normativen Hierarchie: (1) bestätigt-manuell, (2) offizielle Provider-Kapitel (nur bei verifiziertem Provider-Mapping `is_official=true`), (3) eingebettete Publisher-Kapitel, (4) mitgelieferte CUE/Sidecar, (5) Track-als-Kapitel, (6) KI-Vorschlag. Stufen 2–5 dürfen bei bestandener Validierung automatisch aktiviert werden; Stufe 6 niemals — KI-Sets erfordern menschliche Aktivierung, tragen dauerhaft `origin='ai'` und können per DB-CHECK nie als offiziell gespeichert werden. Konflikte zwischen plausiblen Quellen erzeugen Review-Tasks statt stiller Entscheidungen. Manuelle Edits erzeugen neue `manual`-Sets, statt Quell-Sets zu verändern.

## Konsequenzen

* Herkunft und Auswahlgrund jeder aktiven Kapitelstruktur sind jederzeit beweisbar; „zurück zur offiziellen Struktur" ist ein Klick, weil Quell-Sets unverändert erhalten bleiben.
* Artefakte (CUE/M4B) tragen die Herkunftskennzeichnung nach außen (REM-Marker, Metadaten), inklusive expliziter Inoffiziell-Markierung bei KI-Ursprung.
* Mehr Speicher und Modellkomplexität als eine einzelne Kapitelliste — bewusst bezahlt; der Mehrquellen-Konflikt ist der Normalfall.
* Die Hierarchie ist zentral änderbar (eine Stelle im Selector), statt in Werkzeug-Flags verstreut.

## Erwogene Alternativen

Eine Kapitelliste pro Werk mit Überschreiben (verliert Herkunft und Rollback); Confidence-only-Auswahl ohne Stufen (eine sehr „selbstsichere" KI würde offizielle Quellen verdrängen — genau das verbietet Regel 5); Fusion mehrerer Quellen zu einer Mischstruktur (nicht nachvollziehbar, im Fehlerfall unentwirrbar).
