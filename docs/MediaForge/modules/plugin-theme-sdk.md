# Plugin, Theme and Custom CSS System

Status: **P0 Extension Points, P2 vollständiges SDK/Marketplace**

## Plugin Types

```text
Theme
UI Extension
Metadata Provider
Automation / Workflow
Analysis Provider
Engine Adapter
```

Jedes Plugin besitzt ein versioniertes Manifest, Compatibility Range, Permissions und Capability Declaration.

## Theme SDK

`packages/theme-sdk` definiert:

- Design Tokens;
- CSS Variables;
- scoped CSS hooks;
- theme metadata;
- compatibility;
- optional assets/icons.

Standard ist **scoped/safe CSS**. Advanced Custom CSS darf globaler eingreifen, wird aber als explizit unsicherer Modus markiert und darf kritische Security-/Private-Mode-Semantik nicht umgehen.

## Custom CSS

Settings -> Appearance -> Custom CSS bietet:

- Preview;
- validation;
- reset;
- import/export;
- safe/scoped mode;
- optional Advanced Global CSS.

## Plugin SDK

`packages/plugin-sdk` enthält Manifest-Schemas und stabile Extension Points. Plugins dürfen keine impliziten DB-Tabellen anderer Module verändern. Sensitive Permissions sind deklarativ und UI-sichtbar.

## Marketplace

Späterer Marketplace zeigt Typ, Version, Signatur/Quelle, Berechtigungen, Compatibility und Update-History. Installation ist nicht Voraussetzung für MediaForge Core.
