# Claude Implementation Directive

Diese Datei ergänzt die Master-Spezifikation für Claude Code.

## Vor jeder Implementierung

1. `CURRENT_PHASE.md` lesen und reale Phase respektieren.
2. `MediaForge_Master_Engineering.md` lesen.
3. `PRODUCT_DECISIONS_2026-08.md` lesen.
4. betroffene Modul-/Architekturdocs lesen.
5. keine spätere Phase vorziehen, nur weil sie interessanter ist.

## Harte Regeln

- Nie behaupten, ein späteres Ziel sei bereits implementiert.
- `CURRENT_PHASE.md` nur ändern, wenn Code + Tests den neuen Stand tatsächlich beweisen.
- Keine Fork-/ISO-/AI-Audio-Arbeit vor dem dafür vorgesehenen Gate.
- Keine sichtbare Weiterleitung auf Jellyfin/Stash/ABS als endgültige UX.
- PostgreSQL-Identität nicht durch Engine-IDs ersetzen.
- Adult Zero Leak serverseitig erzwingen.
- Disc-Auto-Mapping niemals aus Confidence ableiten.
- Originaldateien nicht ungefragt verändern/umbenennen.
- TypeScript bleibt verbindlich.
- UI-Referenzen unter `ui-ux/reference/` vor großen Frontend-Arbeiten ansehen.
- Jede neue Seite muss dieselbe Design-Sprache verwenden.
- Keine stillen Scope-Erweiterungen oder riskanten Fallback-Heuristiken.

## Abschluss eines Arbeitspakets

Claude soll liefern:
- geänderte Dateien;
- rationale Zusammenfassung;
- Tests/Gates und Ergebnis;
- bekannte Limits;
- keine Commits/Tags/Pushes ohne ausdrückliche Anweisung des Benutzers.
