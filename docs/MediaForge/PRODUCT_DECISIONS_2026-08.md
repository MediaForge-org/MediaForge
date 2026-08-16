# Product Decisions — August 2026

Status: **verbindliche Ergänzung zur Master-Spezifikation**. `CURRENT_PHASE.md` beschreibt weiterhin ausschließlich den real implementierten Stand.

## Produkt und Architektur

1. **Eine sichtbare App:** MediaForge ist die einzige normale Benutzeroberfläche.
2. **Ein GitHub-Monorepo:** Server, Web, Engines, Services, Contracts, Platform und Tests sollen langfristig im selben Repository liegen.
3. **API-first Web:** React + TypeScript + React Router gegen MediaForge API v1 ist die Zielarchitektur. Inertia wird nicht weiter als langfristige Grenze ausgebaut.
4. **Laravel Control Plane:** PHP/Laravel bleibt für Auth, Katalog, Provenienz, Review, Orchestrierung, API, Audit und Settings.
5. **PostgreSQL dauerhaft:** kanonische Source of Truth; Engine-Datenbanken ersetzen MediaForge-IDs nie.
6. **Redis technisch, nicht kanonisch:** Queue/Cache/Locks/Realtime, keine fachliche Master-Datenbank.
7. **Polyglot bewusst:** C# Video, Go Adult, Node/TS Audio, Rust MediaTools, Python AI sind erlaubt, wenn die Verantwortung klar ist.
8. **Rust für neuen nativen MediaForge-Code:** C++ nur, wenn konkrete Library-/Performance-Gründe es verlangen.
9. **Contracts zentral:** OpenAPI/JSON Schema/Events definieren Interoperabilität.
10. **Keine Cross-DB-Coupling:** Komponenten sprechen Contracts, nicht fremde Tabellen.

## UI, Routing und Distribution

11. **Premium Design:** alle Referenzscreens + schriftliche UI-Spezifikation bilden gemeinsam die Quality Baseline.
12. **Schöne Deep Links:** z. B. `/serien/supernatural/staffel-01/01-die-frau-in-weiss`.
13. **Adult ebenfalls schön:** standardmäßig `/adult/...` mit sprechenden Slugs.
14. **Strict Private URLs optional:** Benutzer kann für Adult opake/neutralere URLs aktivieren.
15. **Opaque Streams:** Playback-Sessions verwenden kurzlebige `/_stream/...` URLs; Video läuft nicht unnötig durch PHP.
16. **Official Docker Distribution:** später offizieller Compose-/GHCR-Release aus dem Monorepo.

## Medienmodell

17. **Scene != File.**
18. **Episode != File.**
19. **Audiobook Chapter != File.**
20. **Cut != Technical Edition.**
21. **Work Graph:** medienübergreifende Beziehungen sind ausdrückbar.
22. **Multiple Episode Orders:** Aired/DVD/Blu-ray/Streaming/Production/Custom parallel.
23. **Cross-Edition Progress Mapping:** optional, nur bei semantisch kompatiblen Editionen.
24. **Originale standardmäßig immutable:** Transformationen erzeugen neue Editions/Artifacts; explizit bestätigte User-Aktionen dürfen Dateien splitten/verschieben/löschen.

## Adult

25. **Adult Zero Leak:** gesperrt vollständig unsichtbar in normaler UI/API/Notifications/Preloads.
26. **Stash-derived Adult Engine:** direkter, lizenzkonformer Fork als Media Engine.
27. **Library-driven Sync:** Full Sync nur für lokal relevante Performerinnen plus manuell gepinnte Ausnahmen.
28. **Remote Images standardmäßig remote:** Cache optional, nicht blind alles spiegeln.
29. **Naming Standard:** `Studio - YYYY-MM-DD - Performer(s) - Titel`, weitere Parser konfigurierbar, kein ungefragtes Rename.
30. **Field-level Provenance:** Studio-/TPDB-/StashDB-/Filename-Werte bleiben getrennt nachvollziehbar.
31. **Mehrere Datumsarten:** production/release/studio_publish/first_seen usw.
32. **Scene Lineage:** Original, Re-release, Compilation, Alternate Edit und lokale Editionen nicht künstlich als separate Scenes zählen.
33. **Hierarchische Taxonomie:** Base Event + Attribute, nicht nur flache Tags.
34. **Full Coverage Analysis:** vollständige Video-/Audio-Laufzeit wird analysierbar; Coverage und Confidence getrennt.
35. **Timestamp Events:** Tags/Sound Events besitzen genaue Start-/Endzeiten und sind im Player anspringbar.
36. **Audio Tags:** z. B. crying/screaming/laughing usw. als zeitbezogene Events.
37. **Evidence/Verification:** AI-Tags bleiben nachvollziehbar/korrigierbar.
38. **Checked-absent möglich:** „geprüft und nicht vorhanden“ unterscheidet sich von „nie geprüft“.

## Acquisition

39. **Acquisition Center:** Download-/Import-Orchestrierung in MediaForge UI.
40. **User-provided NZB/Torrent/Magnet:** SABnzbd/qBittorrent als Clients hinter Contracts.
41. **Staging-first:** Download nie ungeprüft direkt in finale Library.
42. **Import Sandbox:** Match/Probe/Duplicate/Quality/Rename/Move vor finalem Write.
43. **Keine Piraterie-Suchmaschine:** offizielle/benutzerkonfigurierte Links und generische Downloader ja, Access-Control-/DRM-Bypass nein.
44. **Source-Capped Quality:** offizielle Source Quality getrennt von Encode/AI Upscale.

## Audiobooks

45. **Audiobook Work/Edition/File getrennt.**
46. **Official Chapter Discovery:** Single-file Hörbücher können passende offizielle Kapitel erhalten.
47. **Edition muss eindeutig sein:** Narrator/Language/Publisher/IDs/Duration werden abgeglichen.
48. **CUE/JSON Sidecars:** portable Kapitel optional.
49. **Storage Strategy User Choice:** logisch-only, Sidecar, oder physische Chapter Files.
50. **Original Keep Default:** Archivieren/Löschen nur explizit.
51. **Transcript Semantic Search später:** direkte Treffer auf Audiopositionen.

## Serien/Filme/Disc/AI

52. **Intro/Recap/Credits/Preview Segments:** später automatisch/manuell, editionsbewusst.
53. **Film Cuts zweistufig:** Cut und technische Edition nicht vermischen.
54. **Extras als echte Objekte.**
55. **Disc/ISO bleibt:** ISO/BDMV/VIDEO_TS, Menüs, Episoden-Watch-State.
56. **Disc Verified-only:** kein Confidence-basiertes Auto-Mapping; sekundengenaue/editionstreue Evidenz erforderlich.
57. **Audio Upscaler bleibt später:** Ergebnis als rekonstruierte Edition, nie als echtes Original ausgeben.

## Ergänzungen vom 16. August 2026

- Web-Ziel: React Router **Framework Mode** + Vite; Next.js wird nicht als zweiter Full-Stack-Server eingeführt.
- Plugin-System umfasst Themes, UI Extensions, Metadata Provider, Automation, Analysis Provider und Engine Adapter.
- Themes erhalten Design Tokens/scoped CSS; Custom CSS bleibt möglich, global nur Advanced/opt-in.
- AI/3D ist vollständig optional; Model Downloads erfolgen erst nach Aktivierung.
- 3D Performer Reconstruction ist Multi-Scene, revisioniert und provider-abstrakt.
- Tattoo Coverage für weibliche Adult-Performer ist flächenbasiert über die gesamte Körperoberfläche; Tattoo Count ist sekundär.
- Anatomy Regions werden versioniert und deutlich feiner als arms/torso/legs modelliert.
- Große AI-/3D-Artefakte liegen im content-addressed Artifact Store statt PostgreSQL-BLOBs.
- AI Model Registry + Evaluation/Golden Dataset + Resource Scheduler sind Teil der langfristigen Plattform.
- Standard-Git-Workflow: kleine grüne Inkremente direkt auf main; Branches nur optional für besonders riskante Experimente; kein kaputter Commit.
- Die Anzahl der nummerierten Claude-Prompts bleibt 720.

## Ergänzungen vom 17. August 2026

58. **MediaForge owns the product surface:** integrierte Programme liefern Backend-Fähigkeiten; normale Benutzer arbeiten in MediaForge UI/API. Native UIs sind Admin-/Fallback-Werkzeuge.
59. **Forks früh, Cutover später:** Jellyfin/Stash/Audiobookshelf werden in Track 02 als gepinnte Upstream-Baselines ins Monorepo geholt; Tracks 26–28 vollenden die Integration.
60. **Unveränderte Managed Upstreams:** SABnzbd, qBittorrent, Prowlarr, Sonarr, Radarr und Whisparr bleiben standardmäßig unveränderte Upstream-Komponenten mit MediaForge Lifecycle/Compatibility Layer.
61. **Breite Indexer-Unterstützung:** Newznab/Torznab/Prowlarr/Jackett-kompatible Definitionen, native Provider-Plugins, RSS und Browser Companion statt kleiner Hardcoded-Liste.
62. **AcquisitionBlueprint:** Zielmedium, Release, Naming, Downloader, Seeding, Disc/Remux/Transcode und Library-Policy werden vor der Automation explizit modelliert.
63. **MediaForge Naming Authority:** SAB/qBit liefern/seed-en; MediaForge entscheidet finale Library-Namen. Usenet-Passwörter sind Secrets und niemals finale Dateinamen.
64. **Torrent-safe Library:** Hardlink bevorzugt, Reflink/Copy Fallback; aktives Seed-Payload nicht blind auf Dateisystemebene umbenennen.
65. **Post-Processing DAG:** Probe/Identify/Disc/Remux/Rename/Subtitle/Transcode/Verify/Import/Cleanup sind resumable/idempotente Schritte.
66. **Disc-to-library Automation:** verifizierte Serien-ISOs dürfen optional Episoden/Extras als MKV remuxen; danach optional H.264, H.265 und/oder AV1 als abgeleitete Editionen erzeugen.
67. **Erste Sprachklasse:** Deutsch (Default), English (UK), Italiano, Español, Français sind initial vollständig unterstützte UI-Lokalisierungen.
68. **Professioneller Metadata-Translation-Fallback:** fehlen hochwertige Metadaten in der Ziel-Sprache, erzeugt ein austauschbarer lokaler/Cloud-/Plugin-Übersetzer eine qualitätsgesicherte Übersetzung; Original + Provenienz bleiben erhalten.
69. **Translation Memory/Glossary:** Wiederverwendung, Terminologie und Kostenkontrolle sind Teil der Plattform; Cloud-Übersetzung bleibt optional.
70. **Weitere Sprachen später:** zusätzliche Locales werden schrittweise ergänzt und dürfen die fünf initialen First-Class-Locales nicht verwässern.
71. **Artwork correctness:** UI-Referenzbilder sind Templates; das echte Produkt zeigt nur Artwork des tatsächlich gematchten Media-Objekts, andernfalls Placeholder/Review statt falschem Cover.
