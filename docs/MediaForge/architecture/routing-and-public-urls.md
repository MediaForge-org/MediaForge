# Routing, Deep Links und sprechende URLs

Status: verbindliche Produktentscheidung

## Ziel

MediaForge benutzt menschenlesbare URLs für sichtbare Medienseiten. Interne Identität bleibt ULID-basiert. URL-Slugs sind Presentation-/Navigation-State, nicht Primärschlüssel.

## Beispiele

### Serien

```text
/serien/supernatural
/serien/supernatural/staffel-01
/serien/supernatural/staffel-01/01-die-frau-in-weiss
```

### Filme

```text
/filme/inception-2010
/filme/blade-runner-1982
```

### Hörbücher

```text
/hoerbuecher/der-hobbit-rob-inglis
/hoerbuecher/der-hobbit-rob-inglis/kapitel/05-raetsel-im-dunkeln
```

### Collections

```text
/sammlungen/star-wars
/sammlungen/supernatural-universum
```

### Adult

Standardmäßig ebenfalls sprechend:

```text
/adult/performer/luna-hart
/adult/studios/example-studio
/adult/scenes/example-studio/2024-05-12/morning-light
/adult/sammlungen/favoriten
```

Adult wird durch Privacy/Authorization geschützt, nicht durch absichtlich schlechte URLs.

## Strict Private URLs

Optionaler Modus für Benutzer, die Browser-History/Proxy-Logs zusätzlich neutral halten wollen:

```text
/adult/i/01K...
/adult/p/01K...
```

Der Modus ist eine Benutzereinstellung. Er ändert keine kanonischen IDs und keine Scene-/Performer-Namen in MediaForge.

## Slug-Regeln

- lowercase;
- Leerzeichen -> `-`;
- `ä -> ae`, `ö -> oe`, `ü -> ue`, `ß -> ss` als robuster Default;
- Sonderzeichen entfernen/normalisieren;
- stabile Kollisionsauflösung, z. B. Jahr oder kurze ID-Komponente;
- Slug-Historie speichern.

## Slug History

```text
entity_slugs
├── id
├── entity_type
├── entity_id
├── locale
├── slug
├── is_current
├── valid_from
└── valid_to
```

Alte Bookmarks dürfen nicht einfach sterben. Ein historischer Slug löst auf dieselbe Entity auf und kann auf die aktuelle kanonische URL weiterleiten.

## API und Streams

Die sichtbare Seite darf schön sein, die API bleibt stabil/ID-orientiert:

```text
GET /api/v1/media/{ulid}
POST /api/v1/playback/sessions
```

Stream-URLs sind absichtlich opaque und kurzlebig:

```text
/_stream/{session-token}/master.m3u8
```

Sie können user-/device-/quality-gebunden und zeitlich begrenzt sein. Der Videostream soll nicht durch PHP gepumpt werden; der Gateway routet ihn zur zuständigen Engine.

## Direktes Reload

Jede sichtbare Deep-Link-URL muss bei direktem Browser-Reload funktionieren. Der Gateway liefert für Frontend-Routen die React-App aus; React Router löst die Seite auf und lädt die Entity über API v1.

## Security

Adult-URLs dürfen im gesperrten Zustand keine Existenz leaken. Autorisierung findet serverseitig statt. Abhängig von der Policy kann eine nicht autorisierte Adult-Entity wie nicht existent beantwortet werden.
