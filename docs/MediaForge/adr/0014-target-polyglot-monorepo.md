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

## 2026-09-27 supersession note

ADR-0028 supersedes the fork-specific assumption that Jellyfin/Stash/Audiobookshelf-derived runtimes
must live inside the MediaForge monorepo. The polyglot-monorepo decision remains accepted for
MediaForge-owned server/web/contracts/Rust/Python code. Specialist upstream media servers are now
integrated through versioned adapters by default.
