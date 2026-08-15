# MediaForge — UI implementation prompt for Claude

Use this document together with all images in `ui-ux/reference/` and `ui-ux/reference-expanded/`.
The images are mandatory references, but they are **not enough on their own**. You must also follow the written visual specification below.

## 1. Core expectation
MediaForge must look like a premium, cinematic, media-first product — not like a generic admin dashboard and not like a lightly skinned Stash/Jellyfin clone.

The interface should feel:
- premium
- modern
- high-density but well-structured
- visually calm despite many features
- content-first and media-first
- polished enough that a user immediately thinks it looks better than stock Stash

## 2. Mandatory visual language
Implement a coherent design system with the following qualities:

- **Dark base:** deep navy / blue-black / charcoal surfaces, not flat pure black everywhere.
- **Accent strategy:** controlled use of purple, cyan, blue and green highlights. Accents must guide attention, not create neon chaos.
- **Rounded surfaces:** medium-large corner radius for cards, dialogs, feature blocks and side panels.
- **Depth:** layered panels, subtle borders, faint glows, soft gradients, clear elevation hierarchy.
- **Typography:** strong hierarchy; large confident page titles; smaller muted support text; compact but readable tabular/secondary content.
- **Card-driven layout:** complex pages are composed from refined cards/panels, not bare tables.
- **Media-forward presentation:** artwork, thumbnails, poster strips, scene stills, waveform/spectrogram views, progress modules and visual evidence should appear where relevant.
- **Status clarity:** setup / processing / production states must be obvious through color, layout and labeled progress/status widgets.
- **Professional polish:** empty states, skeleton/loading states, hover states, validation states and destructive actions must all look intentional.

## 3. What it must NOT look like
Avoid all of the following:
- generic Bootstrap/admin template look
- plain CRUD forms with weak hierarchy
- oversized white/light-gray tables
- inconsistent spacing or random card styles
- crowded “developer tool only” interfaces with no product polish
- a simple Stash reskin with mostly unchanged structure
- visual regressions back to low-fidelity utility UI

## 4. Product-level layout principles
### Home / main dashboard
The home page should behave like a unified command center for the entire media ecosystem.
It should present:
- quick access to modules
- setup or onboarding state when relevant
- currently running operations
- recommended or continue-watching media modules
- quick stats and health summaries
- privacy/private-mode state without exposing adult content publicly

### Library pages
Library views should prioritize content browsing quality:
- polished artwork grids
- excellent spacing and selection states
- strong filtering and sorting affordances
- visible metadata without becoming spreadsheet-like
- easy switch between browsing and management modes

### Detail pages
Performer, scene, movie, show, audiobook and disc detail pages should use:
- a strong hero/header region
- immediate access to key stats
- tabbed sections for overview / metadata / relationships / sources / history / quality
- sidecards for summary info, source links, sync health, collections, notes or related items

### Operational screens
Processing-heavy views (sync, queues, ingest, parser, metadata matching, verification, analytics, audio enhancement) should clearly show:
- live activity
- queue/job states
- progress and throughput
- health and warnings
- actionable controls
- audit/provenance context where needed

## 5. Phase-driven design rule
Many images intentionally show **Phase 1 / Phase 2 / Phase 3**.
Preserve this concept where it adds clarity:
- **Phase 1 / Setup** = configuration, local discovery, onboarding, initial scan, initial record creation
- **Phase 2 / Processing** = matching, syncing, comparing, active jobs, enrichment, live analysis
- **Phase 3 / Production** = final merged record, polished browsing, final results, auditability, export, ongoing use

Claude should not mechanically force the phase layout onto every page. Use it where it helps explain workflow-heavy features.

## 6. Jellyfin influence, but better
The user likes the best Jellyfin UI extensions as a quality direction. That means:
- elegant media browsing
- cinematic presentation
- visually rich cards and hero areas
- smooth module composition

But MediaForge must still have its own design system and stronger management/operations UX than Jellyfin.

## 7. Adult/private mode rule
Private/adult functionality must be:
- hidden from the public/default family-facing home experience
- accessible via private mode / password / PIN / secret or protected path
- clearly visually distinct once unlocked, but still within the same overall design system

Do not surface adult entries openly on the main family-safe landing screen.

## 8. Feature-specific expectations
### Naming parser / library settings
Must look like a premium ingestion/configuration workbench, not a boring settings table.
Use cards, rule lists, preview panels, live parsing examples, progress widgets, duplicate/unmatched summaries and remediation actions.

### Performer and scene sync
Must look rich, connected and media-driven.
Show profile hero, network/collections/source links, scene thumbnails, coverage, sync state and progression from local-only to fully enriched.

### Metadata matching workbench
Must look like a high-trust review tool.
Emphasize side-by-side comparison, evidence, field provenance, confidence, merge action paths and auditability.

### Tasks / queues / sync operations
Must feel operationally mature.
Show queue health, worker state, throughput, failure review, controls, recent completions and observability.

### Analytics & reports
Must feel executive-quality.
Use refined KPI cards, trend charts, scheduled report modules, alert summaries and collection-level insights.

### Audio enhancement/upscaler
Must look like a serious media-processing workstation.
Use waveform/spectrogram panels, preset systems, real-time progress, quality metrics and integration back into the main library.

### Disc / ISO exact verification and provenance
These views must communicate rigor and trust.
Show exact-match policy, evidence collection, hashes, fingerprinting, timeline/runtime equality, snapshot evidence, export packages and explicit pass/fail outcomes.

## 9. How to use the reference images
Do not simply imitate one image literally.
Instead, combine them into one consistent design language.

Treat the images as a modular spec for:
- layout density
- spacing rhythm
- navigation style
- card composition
- media presentation
- color usage
- hierarchy of information
- feature-specific component patterns

## 10. Implementation rule for Claude
Before implementing or restyling any major screen:
1. inspect the relevant image(s)
2. restate in your work log what visual traits must be preserved
3. identify what exact page or component will be changed
4. implement the UI so that the resulting screen clearly matches the written rules and the relevant images
5. note any deliberate deviations and why they were necessary for usability or existing architecture

If there is tension between code convenience and UI quality, favor UI quality unless it causes a major architectural problem.

## 11. Neue verbindliche Feature-Referenzen

Vor UI-Arbeit zusätzlich lesen:

- `FEATURE_SCREEN_SPECIFICATIONS.md`
- `../modules/acquisition-center.md`
- `../modules/adult-analysis-and-taxonomy.md`
- `../modules/audiobook-chapters-and-storage.md`
- `../modules/series-advanced-model.md`
- `../modules/movies-advanced-model.md`

Die Screens `30` bis `41` unter `reference-expanded/` decken neu hinzugekommene Workflows ab. Claude darf aus einem Bild keine fachliche Regel erfinden; die Markdown-Spezifikation entscheidet.

## 12. Informationsarchitektur für komplexe Screens

Wenn eine Seite sehr viele Daten enthält:

1. Hero/Summary oben;
2. primäre Workflow-Aktion klar sichtbar;
3. Tabs für sekundäre Dimensionen;
4. rechte Side Panel Zone für Status/Provenienz/Impact;
5. Details on-demand statt alles gleichzeitig offen;
6. Tabellen nur dort, wo Vergleich wirklich tabellarisch ist;
7. Visual Evidence/Media wenn semantisch hilfreich.

## 13. URL und Routing UX

Breadcrumb und Browser-Route sollen sprechende Slugs widerspiegeln. API-ULIDs dürfen im normalen UI nicht als Hauptnavigation erscheinen.

## 14. Adult UX

Nach Unlock darf Adult genauso hochwertig und sprechend sein wie andere Bereiche. Standardroute `/adult/...`. Privacy wird durch Auth/Zero-Leak erreicht; Strict Private URLs sind optional.

## 15. AI UX

AI-Ergebnisse zeigen immer:

- was erkannt wurde;
- wo (Timestamp/Segment);
- wie vollständig analysiert wurde;
- Confidence;
- Verification State;
- Evidence/Why;
- Correct/Reject Action soweit sinnvoll.

Kein UI-Text wie „AI verified“ ohne definierte Verifikationsregel.
