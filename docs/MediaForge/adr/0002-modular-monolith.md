# ADR-0002: Modularer Monolith statt Microservices

Status: accepted · Bezug: [architecture/overview.md](../architecture/overview.md)

## Kontext

MediaForge umfasst viele fachlich getrennte Module (Disc-Engine, Assembler, Upscaler, Connectoren, Engines) mit sehr unterschiedlichen Ressourcenprofilen. Die naheliegende Frage: eigene Services pro Modul oder eine Codebasis?

## Entscheidung

Eine Laravel-Codebasis als modularer Monolith. Modulgrenzen sind Code-Grenzen (Namespaces, Interfaces, Events), per Architektur-Tests erzwungen, keine Netzwerk-Grenzen. Skalierung und Isolation unterschiedlicher Workloads geschieht über getrennte Queue-Worker-Container desselben Images, nicht über getrennte Services. Ausnahmen sind genau die zwei Stellen, an denen eine Prozessgrenze technisch geboten ist: der media-tools-Kommandodienst (native Binärwerkzeuge) und der ai-worker (Python/GPU-Stack).

## Konsequenzen

* Transaktionen über Modulgrenzen bleiben möglich (Audit + Fachänderung atomar) — mit Microservices wäre das verteiltes Commit-Theater.
* Kein Service-Mesh, keine API-Versionierung zwischen eigenen Komponenten, ein Backup-Gegenstand, ein Log-Strom pro Container-Rolle.
* Die Disziplin verlagert sich in die Modulgrenzen im Code; die Pest-Architektur-Tests sind deshalb Fundamentbestandteil, nicht Nice-to-have.
* Sollte je ein Modul echte Isolation brauchen, ist der Schnitt entlang der bestehenden Modulgrenzen möglich; die Event-/DTO-Verträge sind die vorbereitete Sollbruchstelle.

## Erwogene Alternativen

Microservices pro Modul (Betriebsaufwand im Heimserver-Kontext unverhältnismäßig; Netzwerkfehler als neue Fehlerklasse ohne Nutzen), Plugin-Prozess-Modell à la *arr-Ökosystem (getrennte Apps mit HTTP-Kopplung — genau die Silobildung, die MediaForge beheben soll).
