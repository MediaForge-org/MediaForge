# Serien – Episode Orders, Timeline, Editions und Watch Orders

Priorität: P0 Datenmodell / P1 Feature UI

Referenz: `39_series_episode_order_and_editions.png` und `41_feature_matrix_detailed.png`.

## 1. Mehrere Episodenordnungen

Eine Episode hat eine kanonische Identität, kann aber mehrere Nummerierungen besitzen:

- Aired Order;
- DVD/Blu-ray Order;
- Streaming Order;
- Production Order;
- Absolute Order;
- Custom User Order.

```text
episode_order_entries
├── episode_id
├── order_id
├── season_number nullable
├── episode_number nullable
├── absolute_number nullable
└── source_fact_id
```

Damit werden Disc-/Streaming-Abweichungen nicht durch manuelle Umbenennung „gelöst“.

## 2. Intro/Recap/Credits/Preview Timeline

Kapitelartige Segmenttypen:

```text
recap
intro
main_content
credits
next_episode_preview
commercial_break optional
custom
```

Skip-Einstellungen sind profil-/device-spezifisch.

## 3. Episoden-Editionen

Eine Episode kann mehrere Sources besitzen:

- TV Broadcast;
- Blu-ray;
- UHD;
- WEB;
- lokale Encodes.

Diese sind keine separaten Episode-Entities.

## 4. Multi-Part und Arcs

MediaForge kann Beziehungen speichern:

```text
part_of_story_arc
continues
continued_by
special_between
```

Das beeinflusst nicht zwingend die Staffelnummerierung.

## 5. Watch Orders

User/Provider können Watch Orders definieren, die Serien, Filme, Specials und andere Works verbinden.

Beispiel:

```text
Series S04E12
-> Movie X
-> Series S04E13
```

Watch Orders sind getrennte Objekte mit Quelle, Version und Benutzer-Override.

## 6. Deep Link

```text
/serien/supernatural/staffel-01/01-die-frau-in-weiss
```

Slug ist nicht die Episode-ID.
