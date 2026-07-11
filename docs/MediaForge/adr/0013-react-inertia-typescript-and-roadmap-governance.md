# ADR-0013: React, Inertia und TypeScript sowie Roadmap-Governance

Status: accepted · ersetzt: [ADR-0001](0001-technology-stack.md) ausschließlich hinsichtlich Frontend-Stack und Roadmap-Governance

## Kontext

ADR-0001 legte Vue 3, TypeScript und Inertia.js als Frontend-Stack fest. Seitdem wurde die langfristige Produktplanung präzisiert. Der finale Master-Prompt definiert React als verbindliche Web-Grundlage und ordnet die Entwicklung in die internen Engineering-Phasen V0 bis V34 ein. Das Repository befindet sich weiterhin in V0; vorhandene Core-Bausteine sind deshalb zu stabilisieren, ohne V1- oder spätere Features vorzuziehen.

## Entscheidung

Der verbindliche Web-Frontend-Stack ist React, Inertia.js und TypeScript mit Vite und Tailwind CSS. Vue ist kein Zielstack mehr. Vue-Abhängigkeiten dürfen nur vorübergehend während einer klar begrenzten Migration verbleiben und werden entfernt, sobald der minimale React-Einstieg, die Welcome-Seite, Typecheck und Build funktionieren.

Die V0-bis-V34-Roadmap des finalen Master-Prompts ist der aktuelle Roadmap-Override. Ältere, gröbere Phasenmodelle bleiben historischer Kontext, dürfen aber nicht zur Steuerung neuer Arbeit verwendet werden.

V1 beginnt erst, wenn das V0-Gate vollständig grün ist: reproduzierbare Installation, gültige Composer- und NPM-Lockfiles, Laravel-Start, React-/TypeScript-Build, Tests, statische Analyse, Style-Prüfung, gültige Root- und Dev-Compose-Konfiguration, dokumentiertes Setup und keine getrackten Secrets oder lokalen Build-Artefakte.

## Konsequenzen

* Neue Frontend-Dateien verwenden React und TypeScript; neue Vue-Dateien sind unzulässig.
* Bestehende Theme- und Inertia-Grundlogik wird bei der Migration erhalten, nicht neu erfunden.
* V0-Arbeit bleibt auf Repository-, Build-, Environment-, Docker-, Dokumentations- und Validierungsstabilisierung beschränkt.
* Vorhandene Migrationen, Models, Audit-, Settings-, Checkpoint-, Artifact-, Review- und Architekturtest-Strukturen bleiben erhalten.
* V1-Features wie Auth-Flows, Dashboard-Ausbau und Connector-Diagnostik werden erst nach bestandenem V0-Gate implementiert.
* Mobile-, Desktop-, Adult-, Download-, Disc- und Fork-Arbeit bleibt außerhalb von V0 und V1, soweit die jeweils aktuelle Roadmap nichts anderes ausdrücklich freigibt.

## Erwogene Alternativen

Vue dauerhaft beizubehalten würde der verbindlichen Produktentscheidung widersprechen und eine spätere zweite Migration erzwingen. React als separates SPA ohne Inertia würde unnötige API-Doppelbuchführung in die lokale Web-App einführen. Next.js oder Remix würden ein zusätzliches Framework und Deployment-Modell ohne V0-Nutzen einführen. Eine sofortige große UI-Neustrukturierung wurde verworfen: V0 braucht nur einen minimalen, reproduzierbaren React-Einstieg.
