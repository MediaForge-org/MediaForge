# ADR-0026 — Localization-first product with professional metadata translation fallback

**Status:** Accepted
**Date:** 2026-08-17

## Decision

Initial first-class locales are German (default), English (UK), Italian, Spanish and French. User-facing strings and normalised backend statuses are localized through stable message keys.

When trustworthy metadata is unavailable in the preferred metadata locale, MediaForge may generate a professionally worded translation via an interchangeable local/cloud/plugin TranslationProvider while preserving original text and provenance.

## Consequences

- UI and metadata locale settings are separate.
- Launch locales require complete QA coverage.
- Translation memory/glossaries/provider policies are first-class architecture.
- Paid/cloud translation is optional; MediaForge remains usable without it.
- More locales can be added progressively without changing canonical media identity.
