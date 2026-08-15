# Unified MediaForge Interface

## Produktziel

Ein Login, eine App-Shell, ein Designsystem, eine Suche und ein konsistentes Navigationsmodell.

Die Oberfläche darf intern unterschiedliche Engines nutzen, ohne den Benutzer dorthin weiterzuleiten.

## Bereiche

Normaler Modus kann u. a. enthalten:
- Home;
- Movies;
- Series;
- Music;
- Audiobooks;
- Podcasts;
- Discs (wenn implementiert);
- Collections;
- Search;
- Settings.

Adult ist hier **nicht sichtbar**.

## Home

Home wird aus kanonischen MediaForge-Items/engine-normalisierten DTOs komponiert:
- Continue Watching;
- Continue Listening;
- Recently Added;
- Libraries/Collections;
- Recommendations;
- Server/Library Health nur wenn passend.

Rows dürfen Items verschiedener Engines mischen, sofern UX sinnvoll ist. Engine-Herkunft muss nicht als technisches Label sichtbar sein.

## Detailseiten

Movie/Series/Audiobook/Scene haben domänenspezifische Inhalte, teilen aber:
- Hero;
- Artwork;
- Actions;
- Version/Edition Cards;
- Progress;
- Metadata/Source Panels;
- Related Content;
- Skeleton/Error/Empty States.

## Kein iframe

Fremde Web-UIs sind Developer/Advanced-Fallbacks und nie Standardnavigation.

## Design

Siehe [design-system.md](design-system.md) und Referenzbilder in `reference/`.
