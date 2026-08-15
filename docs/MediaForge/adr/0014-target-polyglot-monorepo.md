# ADR-0014 – Target Polyglot Monorepo and API-first Web

Status: accepted target architecture

## Entscheidung

MediaForge wird als Polyglot-Monorepo strukturiert. Die Web-Zielarchitektur verwendet React + TypeScript + React Router gegen MediaForge API v1. Inertia wird nicht als langfristige Architektur weitergeführt.

Neue native MediaForge-Dienste verwenden bevorzugt Rust; ML-Dienste Python. Jellyfin-/Stash-/Audiobookshelf-derived Engines behalten ihre geeigneten Upstream-Sprachen.

## Konsequenzen

- kurzfristiger Architekturumbau;
- weniger spätere Migration;
- alle Clients können dieselbe API verwenden;
- Claude kann Cross-Language-Änderungen in einem Checkout durchführen;
- Contract-/E2E-Tests werden wichtiger.
