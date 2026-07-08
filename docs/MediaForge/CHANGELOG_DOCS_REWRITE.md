# CHANGELOG_DOCS_REWRITE

## Zusammenfassung

Die Dokumentation wurde auf das neue offizielle Ziel ausgerichtet: **UMPES = MediaForge**, eine vollständig lokale Enhancement Suite für Jellyfin und Audiobookshelf. UMPES ersetzt Jellyfin und Audiobookshelf nicht, sondern erweitert sie mit Dashboard, UI/UX, Metadaten, Suche, AI, Analytics, Health, Workflows, Rules, Backup, Plugin SDK und Developer Center.

## Geänderte Dateien

- `docs/MediaForge/00_Project_Context.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`
- `docs/MediaForge/architecture/overview.md`
- `docs/MediaForge/architecture/deployment.md`
- `docs/MediaForge/architecture/security.md`
- `docs/MediaForge/api/conventions.md`
- `docs/MediaForge/api/webhook-catalog.md`
- `docs/MediaForge/adr/0001-technology-stack.md`
- `docs/MediaForge/database/core-schema.md`
- `docs/MediaForge/connectors/stash.md`
- `docs/MediaForge/connectors/stash/api-mapping.md`
- `docs/MediaForge/ui/design-system.md`
- `docs/MediaForge/99_Expansion_Status.md`
- `docs/MediaForge/developer-handbook/coding-standards.md`
- `docs/MediaForge/developer-handbook/module-cookbook.md`
- `docs/MediaForge/developer-handbook/runbooks.md`
- `docs/MediaForge/modules/review-system.md`
- `docs/MediaForge/modules/watch-state.md`

## Neu erstellte Dateien

- `docs/MediaForge/enhancements/overview.md`
- `docs/MediaForge/enhancements/jellyfin-enhancements.md`
- `docs/MediaForge/enhancements/audiobookshelf-enhancements.md`
- `docs/MediaForge/enhancements/adult-enhancement.md`
- `docs/MediaForge/enhancements/upstream-contributions.md`
- `docs/MediaForge/enhancements/plugin-development.md`
- `docs/MediaForge/enhancements/compatibility-policy.md`
- `docs/MediaForge/features/unified-dashboard.md`
- `docs/MediaForge/features/unified-search.md`
- `docs/MediaForge/features/unified-metadata-engine.md`
- `docs/MediaForge/features/ai-engine.md`
- `docs/MediaForge/features/health-center.md`
- `docs/MediaForge/features/storage-analytics.md`
- `docs/MediaForge/features/developer-center.md`
- `docs/MediaForge/features/backup-center.md`
- `docs/MediaForge/features/rule-engine.md`
- `docs/MediaForge/features/workflow-engine.md`
- `docs/MediaForge/ui-ux/design-system.md`
- `docs/MediaForge/ui-ux/jellyfin-ui-enhancement.md`
- `docs/MediaForge/ui-ux/audiobookshelf-ui-enhancement.md`
- `docs/MediaForge/ui-ux/adult-ui-enhancement.md`
- `docs/MediaForge/ui-ux/dashboard.md`
- `docs/MediaForge/ui-ux/accessibility.md`
- `docs/MediaForge/ui-ux/performance.md`
- `docs/MediaForge/modules/nfo-export.md`
- `docs/MediaForge/roadmap.md`
- `docs/MediaForge/CHANGELOG_DOCS_REWRITE.md`

## Gelöschte Dateien

Keine.

## Verschobene Dateien

Keine. Bestehende Dokumente wurden erhalten; neue Dokumente ergänzen die vorhandene Struktur.

## Terminologieänderungen

- `historische Projektbezeichnung` wurde auf `MediaForge` ausgerichtet.
- `Media-Orchestration-Platform` wurde durch lokale `Enhancement Suite` ersetzt.
- `Single Source of Truth` wurde für UMPES als lokale Referenz- und Enhancement-Schicht präzisiert.
- `Stash-Connector` wurde zu optionalem Stash-Import/Connector umformuliert.
- Mehrere historische relative Markdown-Links wurden korrigiert; der interne Linkcheck meldet `MissingCount=0`.

## Architekturänderungen

- Jellyfin und Audiobookshelf sind klar als Kernsysteme dokumentiert.
- UMPES ist der lokale Enhancement Layer, nicht deren Ersatz.
- API-Kommunikation ist explizit lokale Dienstkommunikation.
- Externe Provider und externe AI sind optional.
- Das Datenmodell speichert lokale Referenzen, IDs, Overrides, Scores, Workflows, Regeln, Plugin-Daten, Audit-Logs, UI-Einstellungen, Adult-Erweiterungsdaten und Suchindexdaten.

## UI-/UX-Ergänzungen

- Neues UI-/UX-Kapitelset unter `docs/MediaForge/ui-ux/`.
- Design-System, Jellyfin UI Enhancement, Audiobookshelf UI Enhancement, Adult UI Enhancement, Dashboard UX, Accessibility und Performance wurden ergänzt.
- Die bestehende Design-System-Referenz wurde mit der UI-/UX-Mission verbunden.

## Adult-Strategie-Änderung

Adult Enhancement ist primär Jellyfin + UMPES. Dokumentiert sind getrennte Sichtbarkeit, Metadaten, Navigation, Performer, Studios, Szenen, Tags, Collections, Favoriten, Watch-State, Suche, Batch-Bearbeitung, Dublettenerkennung, Qualitätsanalyse, lokale Quellen, AI-Tagging und optionale Stash-Migration.

## Stash-Änderung

Stash ist kein Pflichtsystem mehr. Es ist nur noch optionale lokale Datenquelle, Importer, Migrationsquelle oder Inspirationsquelle. Schreibender Stash-Egress ist nicht Teil der Kernstrategie.

## Bestätigung: keine Features wurden entfernt

Keine bestehenden Features wurden absichtlich entfernt. Disc-/Blu-ray-/DVD-Logik, episode-granularer Watch-State, Hörbuch-Kapitel-Assembler, CUE-/M4B-Erzeugung, AI Audio Upscaling, Search, Knowledge Graph, Enrichment, Connector SDK, Plugin SDK, Workflow Engine, Rule Engine, Audit, Health Monitoring, Backup/Restore, Developer Handbook und alle bestehenden Connectoren bleiben erhalten und wurden in die Enhancement-Architektur eingeordnet.

## Offene Risiken

- Historische Formulierungen mit starker Orchestrierungs- oder Wahrheitsschicht-Sprache wurden im kritischen Folge-Review geglättet; verbleibende Treffer dokumentieren die Terminologieänderung im Changelog selbst.
- Die neu erstellten Feature-Dokumente sind bewusst kompakte normative Übersichten und verweisen auf bestehende Detailkapitel; für Implementierungsreife können einzelne UI-/UX-Flows später weiter ausformuliert werden.
- Der Stash-API-Mapping-Detailtext enthält weiterhin technische GraphQL-Mappingdetails; die optionale Rolle ist nun am Anfang und im Hauptconnector normativ geklärt.

## Empfohlene nächste Schritte

1. README ergänzen, sobald ein Projekt-Root-README existiert.
2. Deep-pass über alle Moduldateien, um restliche alte Begrifflichkeit in einzelnen Sätzen zu harmonisieren.
3. UI-/UX-Flows für konkrete Vue/Inertia-Seiten aus den neuen UI-/UX-Zielen ableiten.
4. Compatibility-Matrix für konkrete Jellyfin- und Audiobookshelf-Versionen befüllen, sobald Zielversionen feststehen.
