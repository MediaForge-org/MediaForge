# Review-System (Core-Modul)

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md) (`review_tasks` — SQL-Schema dort normativ, hier die vollständige Lebenszyklus- und Priorisierungsspezifikation), [modules/audit.md](audit.md), [modules/admin-dashboard.md](admin-dashboard.md) (Review-Inbox-UI-Heimat). Konsumenten: jedes Modul, das Automatik unterhalb einer Sicherheitsschwelle betreibt (Disc-Engine, Assembler, Enrichment, Knowledge Graph, Dedup, *arr-Familie, Connector-SDK).

Dieses Kapitel formalisiert den systemweiten Mechanismus für „Automatik ist unsicher, Mensch entscheidet" (Architekturregeln 5 und 11) — das Kernschema definiert die Tabelle, dieses Kapitel definiert das Verhalten: welche Typen es gibt und was sie bedeuten, wie Priorität berechnet wird, wie Snooze/Eskalation funktionieren und welche Regel jedes Modul beim Auflösen einhalten muss.

## Motivation

Elf Module erzeugen Unsicherheits-Fälle: eine Disc-Klassifikation unter der Konfidenz-Schwelle, ein Kapitel-Set-Konflikt, ein Dubletten-Verdacht, ein Metadaten-Drift mit struktureller Wirkung, eine widersprüchliche Provider-Beziehung. Ohne ein gemeinsames Modul entstünde entweder elf verschiedene „Warteschlangen"-UIs (Nutzerverwirrung, keine Priorisierung über Module hinweg) oder — schlimmer — jedes Modul entscheidet in Zweifelsfällen selbst (genau das Architekturregel-5/11-Verbot). Das Review-System ist die eine Warteschlange, die alles aufnimmt, was ein Modul nicht selbst entscheiden darf, mit einer Garantie: **Auflösung geschieht immer als Fach-Action des erzeugenden Moduls**, nie als generischer Review-Resolver — das Review-System verwaltet den Vorgang „diese Entscheidung steht aus", es trifft sie nicht.

## Problemstellung

**Ein geschlossener Typkatalog vs. Erweiterbarkeit.** Der Kernschema-`CHECK` auf `task_type` ist eine geschlossene Aufzählung (`disc_episode_mapping`, `media_match`, `duplicate_suspect`, `chapter_proposal`, `unexpected_media_kind`, `mass_deletion`, `connector_conflict`, `metadata_conflict`) — jeder neue Typ ist eine Migration, kein Laufzeit-Freitext (dasselbe Prinzip wie die Kantentypen des Knowledge Graph: kuratiert, nie Betreiber-Freitext). Das Modul muss trotzdem so gebaut sein, dass ein neuner Typ trivial hinzukommt.

**Priorität ist nicht gleich Dringlichkeit.** Ein `mass_deletion`-Review (potenziell hunderte Dateien) ist objektiv dringender als ein einzelner `chapter_proposal`, aber 200 offene `duplicate_suspect`-Reviews derselben Bibliothek summieren sich zu einem Handlungsbedarf, den ein einzelner hochprioritärer Fall nicht hat. Die Inbox braucht eine Sortierung, die beides abbildet, ohne beliebig zu wirken.

**Snooze vs. Verfall.** Manche Reviews sind Anfang zeitkritisch (`mass_deletion` — je länger offen, desto mehr Scans häufen sich an ungeklärter Basis), andere sind es nicht (`chapter_proposal` einer Nischen-Hörbuch-Ecke kann Wochen warten). Ein einheitliches Snooze/Verfalls-Modell muss beides erlauben, ohne dass „lange nicht angeschaut" automatisch „irrelevant" bedeutet.

**Deduplikation über Zeit.** Der partielle Unique-Index verhindert zwei offene Reviews desselben Typs/Subjekts gleichzeitig — aber ein Review, das aufgelöst und dann erneut ausgelöst wird (Disc neu analysiert, wieder unsichere Klassifikation), muss sauber eine neue Zeile erzeugen, ohne die Historie der ersten zu verlieren.

## Analyse bestehender Lösungen

**Sonarr/Radarr-„Wanted"-Listen** sind das Vorbild für „Arbeitsvorrat als eigene, priorisierte Ansicht" — aber sie kennen nur einen Fall (fehlende Datei), keinen Typkatalog. **GitHub-Issues mit Labels** zeigen das Label/Priorität/Zuweisungs-Muster, das die Inbox-Filter übernehmen (Typ als Label-Äquivalent, Priorität als eigenes Feld). **E-Mail-Snooze** (Gmail/Superhuman-Klasse) ist das direkte Vorbild für die Snooze-Mechanik: ein Review verschwindet bis zu einem Zeitpunkt oder Ereignis, taucht dann wieder auf — ohne als erledigt zu gelten.

## Architekturentscheidung

**Typkatalog mit fester Bedeutung je Zeile** (die acht Kernschema-Typen, hier vollständig erklärt):

| Typ | Erzeuger | Bedeutet | Auflösende Action |
|---|---|---|---|
| `disc_episode_mapping` | Disc-Engine | Klassifikation/Mapping unter Konfidenz-Schwelle | `ConfirmDiscEpisodeMapping`/`RejectDiscEpisodeMapping`/`RemapDiscPlaylist` |
| `media_match` | Connector-SDK, *arr-Familie | Katalog-Zuordnung unsicher (Titel-Ähnlichkeit statt ID-Treffer) | modul-spezifische Match-Bestätigungs-Action |
| `duplicate_suspect` | Dedup/Fingerprinting | Dubletten-Kandidat über Schwelle | Merge-/Dismiss-Action des Dedup-Moduls |
| `chapter_proposal` | Assembler | Chapter-Set-Aktivierungs-Konflikt oder KI-Vorschlag wartet auf Bestätigung | `SelectActiveChapterSet` |
| `unexpected_media_kind` | Scan-Pipeline (Fundament) | Classifier erkennt Medientyp, der nicht zur Bibliotheks-Erwartung passt | Katalog-Korrektur-Action (Bibliothek/Typ anpassen) |
| `mass_deletion` | Scan-Pipeline (Fundament) | Lösch-Dämpfung ausgelöst (> N % verschwundene Dateien) | Bestätigungs-/Verwerfungs-Action des Scanners |
| `connector_conflict` | Connector-SDK | Watch-State-/Monitoring-Konflikt zwischen MediaForge und Gegenstelle | `ResolveWatchStateConflict` bzw. modul-spezifisch |
| `metadata_conflict` | Enrichment, Disc-Engine (Nachverrechnungs-Fall) | struktureller Drift oder Watch-State-Rücknahme-Frage | Enrichment-Drift-Auflösung bzw. Disc-Playback-Umbuchung |

**Priorität als abgeleiteter Wert, nicht als freies Feld**: `priority` (`low`/`normal`/`high`, Kernschema) wird vom erzeugenden Modul bei `CreateReviewTask` gesetzt, aber die **Inbox-Sortierung** kombiniert sie mit zwei weiteren, hier normierten Faktoren: Alter (linear ab Erzeugung) und Typ-Gewicht (eine feste Tabelle, unten) — die resultierende Sortier-Kennzahl ist eine reine Anzeige-/Sortierberechnung (kein gespeichertes Feld, Regel-8-Analogon: nichts, was aus vorhandenen Feldern ableitbar ist, wird materialisiert, außer aus Performance-Gründen — hier ist die Berechnung günstig genug für Live-Sortierung, siehe Performance).

```
sort_score = priority_weight(priority) × 100
           + type_weight(task_type)
           + min(age_days, 30)              -- gedeckelt: ein 90 Tage altes Review ist nicht automatisch
                                             --  dringender als ein frisches high-priority Review
```

| `task_type` | `type_weight` | Begründung |
|---|---|---|
| `mass_deletion` | 50 | potenzieller Datenverlust bei Untätigkeit |
| `connector_conflict` | 30 | Watch-State-Referenzstand divergiert aktiv |
| `metadata_conflict` (structural) | 30 | betrifft laufende Mappings/Watch-States |
| `duplicate_suspect` | 20 | Speicherverschwendung, aber reversibel |
| `disc_episode_mapping` | 15 | ein einzelnes Werk, gut isoliert |
| `unexpected_media_kind` | 15 | ein einzelnes Item |
| `media_match` | 10 | typischerweise Konnektor-Bestand, viele ähnliche Fälle |
| `chapter_proposal` | 10 | selten zeitkritisch |

**Snooze statt Verfall als Primärmechanismus**: `snoozed_until`-Feld (Erweiterung der Kernschema-Tabelle, siehe SQL unten) — ein gesnoozter Review verschwindet aus der Standard-Inbox-Ansicht, bleibt aber `status='open'` (nicht `dismissed`) und taucht nach Ablauf automatisch wieder auf. `expired` (Kernschema-Status) ist ein separater, seltener Fall: nur für Reviews, deren zugrundeliegendes Subjekt sich unabhängig erledigt hat (z. B. ein `duplicate_suspect`, dessen eine Datei zwischenzeitlich gelöscht wurde) — automatisch von einem Sweeper erkannt, nie durch bloßen Zeitablauf.

## Datenmodell-Erweiterung (Kernschema-Ergänzung)

```sql
ALTER TABLE review_tasks ADD COLUMN snoozed_until TIMESTAMPTZ;
ALTER TABLE review_tasks ADD COLUMN snooze_reason TEXT;

CREATE INDEX review_tasks_snoozed_idx ON review_tasks (snoozed_until)
    WHERE status = 'open' AND snoozed_until IS NOT NULL;
```

Die Standard-Inbox-Query (`status IN ('open','in_review') AND (snoozed_until IS NULL OR snoozed_until <= now())`) nutzt den bestehenden partiellen Index (`review_tasks_open_idx`, Kernschema) plus den neuen Snooze-Index als Ausschlussfilter — keine neue Query-Klasse, nur ein zusätzliches Prädikat.

## Lebenszyklus (vollständig)

```
CreateReviewTask (dedupliziert über partiellen Unique-Index)
        │
        ▼
      open ──────► in_review (Benutzer öffnet die Detailfläche; optimistische Markierung,
        │            kein Lock — zwei Manager können gleichzeitig ansehen)
        │
        ├──► SnoozeReviewTask ──► open (snoozed_until gesetzt) ──► automatisch wieder
        │                                                          sichtbar nach Ablauf
        │
        ├──► [Fach-Action des erzeugenden Moduls] ──► resolved (resolution-JSONB gefüllt,
        │                                              resolved_by gesetzt)
        │
        ├──► DismissReviewTask ──► dismissed (nur wo fachlich zulässig — Kernschema-Regel,
        │                          Modulkapitel legt fest, ob Dismiss ohne Fachwirkung erlaubt ist;
        │                          z. B. media_match: Dismiss = „bleibt unmatched", zulässig;
        │                          disc_episode_mapping: Dismiss unzulässig, nur Reject/Confirm/Remap)
        │
        └──► [Sweeper erkennt erledigtes Subjekt] ──► expired (automatisch, mit Diagnose-Notiz)
```

**Dismiss-Zulässigkeit je Typ** (Ergänzung, die im Kernschema fehlt und hier normiert wird):

| Typ | Dismiss zulässig? | Begründung |
|---|---|---|
| `media_match` | ja | „bleibt unmatched" ist ein valider Dauerzustand |
| `duplicate_suspect` | ja | „keine Dublette" ist eine valide Entscheidung |
| `chapter_proposal` | ja (verwirft den Vorschlag, keine Aktivierung) | |
| `unexpected_media_kind` | ja | „Klassifikation war richtig, Bibliothekserwartung anpassen" ist ein akzeptierter Weg |
| `disc_episode_mapping` | **nein** | erfordert immer eine explizite Reject/Confirm/Remap-Entscheidung (Architekturregel 11 verbietet „einfach ignorieren" bei Watch-State-Konsequenz) |
| `mass_deletion` | **nein** | erfordert immer Bestätigen-oder-Verwerfen der Löschungen |
| `connector_conflict` | **nein** | ein Konflikt ohne Entscheidung bleibt ein Konflikt — Dismiss würde ihn unsichtbar, aber nicht gelöst machen |
| `metadata_conflict` | **nein** | wie oben |

Diese Tabelle ist der normative Vertrag, den `DismissReviewTask` bei jedem Aufruf gegen `task_type` prüft — ein Dismiss-Versuch auf einen nicht zulässigen Typ scheitert mit einem Fachfehler, nicht mit stillem Erfolg.

## Laravel-Klassen (vollständig)

| Klasse | Typ | Vertrag |
|---|---|---|
| `CreateReviewTask` | Action | wie Kernschema; zusätzlich: Dedup-Prüfung gegen `snoozed`-Reviews (ein gesnoozter Review desselben Typs/Subjekts wird **reaktiviert** statt eine zweite Zeile zu erzeugen — Snooze ist kein Weg, den partiellen Unique-Index zu umgehen) |
| `SnoozeReviewTask` | Action | `snoozed_until` + `snooze_reason`; Audit; setzt nie `status` um (bleibt `open`) |
| `DismissReviewTask` | Action | prüft Dismiss-Zulässigkeits-Tabelle gegen `task_type`; Audit |
| `ReviewInboxQuery` | Service (pure) | `sort_score`-Berechnung (oben) als reine Funktion über geladene Reviews — testbar ohne DB-Zugriff |
| `SweepExpiredReviewsJob` | Job (Scheduler, täglich) | prüft je offenem Review, ob das Subjekt noch „review-würdig" ist (modul-spezifische `isSubjectStillRelevant()`-Callbacks, von jedem Erzeuger-Modul beigesteuert — fünfte, kleine Anwendung des Registry-Musters, [contracts-reference.md](../developer-handbook/contracts-reference.md)); Nicht-mehr-relevant ⇒ `expired` |

## API-Endpunkte (Ergänzung zum Kernschema/Admin-Dashboard)

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/reviews?type=&priority=&status=` | Inbox-Liste, `sort_score`-sortiert | manager |
| `POST /api/v1/reviews/{ulid}/snooze` | Snooze mit Dauer oder Zieldatum | manager |
| `POST /api/v1/reviews/{ulid}/dismiss` | Dismiss (Typ-Prüfung greift) | manager |
| `GET /api/v1/reviews/types` | Typkatalog mit Beschreibung, Dismiss-Zulässigkeit, `type_weight` | manager |

Die eigentliche **Auflösung** hat bewusst **keine** eigene Route in diesem Modul (Architekturentscheidung: „Auflösung ist immer eine Action des jeweiligen Fachmoduls") — sie läuft über die Modul-eigenen Routen (`POST /disc-mappings/{ulid}/confirm` usw., [Disc-Engine-API](disc-engine/api-reference.md) u. a.), die den Review als Nebeneffekt schließen.

## UI (Ergänzung zu [admin-dashboard.md](admin-dashboard.md))

**`Reviews/Inbox`**: Filterleiste (Typ, Priorität, „inkl. gesnoozt" umschaltbar), Liste sortiert nach `sort_score`, jede Zeile mit Typ-Icon, Alters-Anzeige, Snooze-Button (Schnellauswahl „1 Tag/1 Woche/bis Subjekt sich ändert") und Direktlink in die auflösende Modul-UI. Snooze-Dialog zeigt die Reaktivierungs-Bedingung transparent, wenn eine ereignisbasierte Option gewählt wird (statt reiner Zeitdauer — z. B. „bis nächster Scan dieser Bibliothek", implementiert als sehr naher `snoozed_until` plus Event-Listener, der bei Eintreffen des Ereignisses `snoozed_until` vorzieht). Design-System-Konformität: Typ-Farben folgen `color-status-*`/`color-origin-*` je nach Erzeuger-Herkunft ([ui/design-system.md](../ui/design-system.md)).

## Edge Cases

* **Review-Typ-Erweiterung ohne Dismiss-Regel**: ein neuer `task_type` ohne Eintrag in der Dismiss-Zulässigkeits-Tabelle wird konservativ als „Dismiss unzulässig" behandelt (Fail-Closed, nicht Fail-Open) — die Tabelle hier ist bei jeder Typ-Erweiterung migrationspflichtig zu ergänzen, sonst bleibt der neue Typ ohne Dismiss-Möglichkeit hängen (sichtbarer Fehlzustand, kein stiller Bug).
* **Zwei Manager lösen gleichzeitig auf**: keine Pessimistic Locks (Kernschema-Philosophie: Konsistenz kommt aus Constraints/Transaktionen, nicht aus Locks als Korrektheitsgarantie) — die zweite Fach-Action scheitert an der bereits geänderten Fach-Entität (z. B. `disc.mapping_not_confirmable`, [Disc-Engine-Fehlerkatalog](disc-engine/api-reference.md)) mit klarer Fehlermeldung, der Review selbst bleibt konsistent (`resolved_by` trägt den ersten Erfolgreichen).
* **Snooze-Ereignis tritt nie ein** (z. B. „bis nächster Scan", aber die Bibliothek wird deaktiviert): Review bleibt gesnoozt bis zum Fallback-Maximaldatum (Setting, Default 90 Tage), danach reaktiviert der Sweeper es regulär als offen — kein Review verschwindet für immer durch ein nie eintretendes Ereignis.
* **Massenauflösung** (Rule-Engine-Aktion `create_review` erzeugt hunderte Reviews desselben Typs binnen Minuten, z. B. nach einem Enrichment-Provider-Wechsel): keine automatische Bündelung in diesem Modul (anders als die Health-Monitoring-Digest-Bündelung) — die Inbox-Sortierung nach `sort_score` mit Typ-Gruppierung im UI reicht, weil Reviews im Gegensatz zu Notifications nicht „gepusht" werden, sondern in einer Liste warten.

## Performance

`sort_score` wird zur Anzeigezeit über die geladene Seite (paginiert, ≤ 50 Zeilen, [Query-Katalog](../database/query-catalog.md) Q-20) berechnet — kein materialisiertes Sortierfeld nötig, da die Inbox nie über den gesamten Bestand sortiert, nur über die aktuell offene Menge (typischerweise < 1000 Zeilen, Fundament-Mengengerüst). Der tägliche Sweeper läuft in Chunks über offene Reviews mit modul-spezifischen `isSubjectStillRelevant()`-Aufrufen — je Modul ein Batch-Query, nicht N Einzelaufrufe.

## Security

Inbox und Auflösung sind `manager`-Fläche (Kernschema); Snooze-Kommentare (`snooze_reason`) unterliegen derselben Denyliste wie Audit-Kontext (keine Secrets). Restriktive Sichtbarkeit ([optionaler Stash-Import/Connector](../connectors/stash.md)): Reviews zu Subjekten in `restricted`-Bibliotheken erscheinen nur für Manager mit `library_grants`-Berechtigung — dieselbe Sichtbarkeitsprüfung wie überall sonst, hier auf die Inbox-Query angewendet.

## Tests

Dedup-über-Snooze-Test (ein gesnoozter Review wird bei erneutem `CreateReviewTask`-Aufruf reaktiviert, nicht dupliziert — Property-Test gegen den partiellen Unique-Index). `sort_score`-Tabellentest (bekannte Kombinationen aus Priorität/Typ/Alter ⇒ erwartete Reihenfolge). Dismiss-Zulässigkeits-Matrix (alle acht Typen × Dismiss-Versuch ⇒ erwartetes Ergebnis, inkl. der vier verbotenen Fälle als Fachfehler). Sweeper-Konvergenz (künstlich erledigtes Subjekt ⇒ `expired` beim nächsten Lauf). Gleichzeitigkeits-Test (zwei Auflösungsversuche, zweiter scheitert an der Fach-Entität, Review bleibt konsistent).

## ADR-Verweise

[ADR-0006](../adr/0006-action-level-audit.md) (Auflösung als auditierte Fach-Action). Operationalisiert Architekturregeln 5 und 11 als systemweiten Mechanismus — kein Modul, das diese Regeln erfüllen muss, baut eine eigene Warteschlange.

## Offene Punkte

* **Zuweisung an bestimmte Manager** (statt „irgendein Manager öffnet"): bei Einzelbetreiber-Installationen irrelevant, bei Mehr-Admin-Setups denkbar; kein Bedarf bisher benannt.
* **Review-Vorlagen für Bulk-Kontexte** (200 `media_match`-Reviews nach einem Connector-Erstsync gebündelt abarbeiten, „alle mit Score > 0.9 in einem Klick bestätigen"): das Disc-Engine-Mapping-Review hat eine modul-eigene Sammel-Bestätigung; ein generisches Bulk-Muster über den Typkatalog hinweg ist unspezifiziert.
* **Snooze-Statistik** (wie oft wird welcher Typ gesnoozt — Signal für „Typ ist schlecht priorisiert oder UI ist unklar"): denkbare Metrik für [health-monitoring](health-monitoring/health-check-reference.md), noch nicht aufgenommen.
