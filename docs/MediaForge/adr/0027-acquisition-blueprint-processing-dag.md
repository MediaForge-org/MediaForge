# ADR-0027 — AcquisitionBlueprint and resumable post-processing DAG

**Status:** Accepted  
**Date:** 2026-08-17

## Decision

MediaForge models acquisition intent before download through an `AcquisitionBlueprint` and models post-processing as a resumable/idempotent DAG.

The same orchestration covers Usenet, torrents and manual/local intake while preserving source-specific constraints such as torrent seeding.

## Consequences

- SABnzbd/qBittorrent remain specialised download backends.
- MediaForge owns final classification, naming, provenance and library placement.
- Torrent imports prefer hardlink/reflink/copy while keeping seed payload intact; direct renames go through qBittorrent APIs.
- Disc/ISO workflows can branch into verified episode/extra extraction, MKV remux and optional H.264/H.265/AV1 derived outputs.
- Storage forecasting, resource scheduling and output verification are explicit nodes/gates.
