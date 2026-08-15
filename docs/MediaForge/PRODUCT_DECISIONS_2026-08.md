# Product Decisions — August 2026

Status: verbindliche Ergänzung zur Master-Spezifikation

1. **Eine App:** MediaForge ist die einzige normale Benutzeroberfläche.
2. **Engines intern:** Jellyfin, Audiobookshelf und später Stash-derived Adult laufen hinter MediaForge-Verträgen.
3. **Forks spät:** Direkte Fork-/Bundling-Arbeit bleibt eine späte Phase; Core und UI müssen vorher brauchbar sein.
4. **PostgreSQL dauerhaft:** kanonische Source of Truth; aktuelle Version 17, geplanter Major-Upgrade separat.
5. **React + TypeScript UI:** Vue wird nicht wieder eingeführt.
6. **Premium Design:** Referenzscreens sind verbindliche Qualitätsbaseline.
7. **Adult Zero Leak:** im gesperrten Modus vollständig unsichtbar, auch serverseitig.
8. **Adult später Stash-derived:** direkter Fork als Media-Engine, nicht bloß externer Stash-Link.
9. **Library-driven Adult Sync:** Full Sync nur für lokal relevante Performerinnen oder explizit gepinnte.
10. **Remote Adult Images:** standardmäßig URLs/Metadaten, nicht komplette lokale Spiegelung.
11. **Scene != File:** mehrere lokale Versionen einer Scene.
12. **Adult Naming Standard:** `Studio - YYYY-MM-DD - Performer(s) - Titel`, weitere Parser konfigurierbar; kein ungefragtes Rename.
13. **Disc/ISO bleibt:** ISO, BDMV, VIDEO_TS, Menüs, Episoden-Watch-State und Extras bleiben Produktziel.
14. **Disc verified-only:** keine automatische Episodenzuordnung aus Wahrscheinlichkeit/Minutenlaufzeit.
15. **Sekundengenaue Referenz:** MediaForge recherchiert geeignete externe Quellen selbst; bei Ambiguität kein Mapping.
16. **Audio Upscaler bleibt:** aber erst nach brauchbarem Core; rekonstruiertes Audio wird als Rekonstruktion gekennzeichnet.
17. **Originale unverändert:** Transformationen erzeugen neue Editions/Artifacts.
