# ADR-0001: Technologie-Stack

Status: superseded by [ADR-0013](0013-react-inertia-typescript-and-roadmap-governance.md) · Bezug: [Masterdatei — Technologie-Stack](../MediaForge_Master_Engineering.md)

## Kontext

MediaForge ist eine vollständig lokale Enhancement Suite mit langem Lebenszyklus, betrieben von Einzelpersonen auf Heimservern neben Jellyfin und Audiobookshelf. Anforderungen: ausgereiftes Queue-System (fast alle Fachoperationen sind langlaufende Jobs), starke relationale Datenbank mit Vektor-, Volltext- und Range-Fähigkeiten, produktives Admin-UI mit komplexen Editoren, triviale Installation und trivialer Upgrade-Pfad.

## Entscheidung

Laravel 12 (PHP 8.4), Vue 3 + TypeScript + Inertia.js + Tailwind CSS, PostgreSQL 17 (+ pg_trgm, pgvector), Redis 7, Docker Compose. CPU-/GPU-intensive Verarbeitung läuft nicht in PHP, sondern in dedizierten Containern (media-tools als interner HTTP-Kommandodienst, ai-worker), orchestriert von Laravel-Jobs.

## Konsequenzen

* Ein Deployment-Artefakt, ein PHP-Image für alle App-Rollen; Upgrades sind `compose pull && up -d` plus Migrationen.
* PHP-Grenzen (CPU-Arbeit, Binärparsing) werden per Architekturmuster umgangen, nicht bekämpft: PHP orchestriert, native Werkzeuge rechnen. Dieses Muster ist verbindlich ([architecture/overview.md](../architecture/overview.md)).
* Inertia bindet das eigene UI ohne API-Doppelbuchführung; die REST-API bleibt Dritten vorbehalten und damit klein und stabil.
* TypeScript ist verbindlich für jede neue Vue-Komponente (`<script setup lang="ts">`, [coding-standards.md](../developer-handbook/coding-standards.md)): Jedes Modulkapitel spezifiziert Props-Verträge für seine Seiten (Dokumentkonventionen, Modul-Template Punkt 10) — TypeScript-Interfaces sind die einzige Implementierungsform, die diese Verträge tatsächlich zur Kompilierzeit prüfbar macht, statt sie zur reinen Konvention zu degradieren. Reines JavaScript bliebe hinter der eigenen Spezifikation zurück, die diese Verträge als verbindlich beschreibt.
* Tailwind CSS trägt das Design-System ([ui/design-system.md](../ui/design-system.md)) als Utility-Layer über den semantischen Farbtokens; es ersetzt kein Komponenten-Framework (`resources/js/components/base/` bleibt die einzige gemeinsame Basis, [coding-standards.md](../developer-handbook/coding-standards.md)).
* PostgreSQL-Exklusivfeatures (partielle Unique-Indizes, Range-Typen, Exclusion Constraints, pgvector, deklarative Partitionierung) dürfen und sollen genutzt werden; Datenbankportabilität ist ausdrücklich kein Ziel.

## Erwogene Alternativen

Symfony (mehr Verdrahtung, weniger integrierte Batteries), NestJS/Node (schwächeres ORM-/Queue-Ökosystem für diesen Zuschnitt), Django (gleichwertig, aber PHP-Deployment-Ökosystem im Selfhosting überwiegt), Go (beste Laufzeit, teuerster Eigenbau), MySQL/MariaDB (fehlende Postgres-Features), SQLite (Worker-Parallelität, Backups), Kubernetes (Zielgruppen-Overkill gegenüber Compose). Reines JavaScript ohne Typprüfung wäre der geringere Einrichtungsaufwand, verliert aber genau die Kompilierzeit-Prüfung der Props-Verträge, die über Dutzende Module hinweg konsistent gehalten werden müssen — bei diesem Umfang ein schlechter Tausch.
