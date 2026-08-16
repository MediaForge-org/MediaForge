# Localization and Professional Metadata Translation

Status: **binding product architecture**  
Updated: 2026-08-17

## 1. Launch locales

MediaForge is localization-first. The following locales are first-class and complete for the initial product target:

- `de` — Deutsch, default;
- `en-GB` — English (United Kingdom);
- `it` — Italiano;
- `es` — Español;
- `fr` — Français.

Additional locales are added progressively after the initial target. Long-term breadth should be comparable to mature media-server products, but incomplete later locales must not delay the initial first-class set.

## 2. Quality bar

Translations must be natural, idiomatic and professionally worded. Cheap word-for-word translation is explicitly below the MediaForge quality bar.

Required characteristics:

- terminology consistency;
- context-sensitive wording;
- correct plural/select rules;
- locale-aware dates, times, numbers, sizes and units;
- correct punctuation/capitalisation conventions;
- no concatenated sentence fragments;
- layout resilient to language expansion;
- no untranslated backend/upstream status strings in normal UI.

## 3. UI language is independent from metadata language

Settings are independent:

```text
Interface locale: de
Preferred metadata locale: de
Metadata fallback locale: en-GB
Preferred audio language: de
Preferred subtitle language: en
```

Changing UI locale must not silently rewrite metadata preferences.

## 4. Localisation architecture

Target package:

```text
packages/localization/
├── message-schema/
├── locales/
│   ├── de/
│   ├── en-GB/
│   ├── it/
│   ├── es/
│   └── fr/
├── glossary/
├── translation-memory/
└── validation/
```

UI and backend code use stable message keys. Hard-coded user-visible strings are rejected by QA except explicitly documented developer-only diagnostics.

## 5. Normalise upstream statuses before translation

Never display arbitrary upstream English strings as the canonical MediaForge status.

```text
qBittorrent: "Downloading metadata"
 -> MediaForge enum/status: DOWNLOAD_METADATA
 -> locale message:
    de: "Metadaten werden geladen"
    en-GB: "Downloading metadata"
    ...
```

The same rule applies to SABnzbd, Prowlarr, Sonarr, Radarr, Whisparr and all internal engines.

## 6. Professional metadata translation fallback

When authoritative metadata in the user's preferred metadata language is incomplete, MediaForge automatically fills missing localised fields using a configured professional translation engine.

Priority:

```text
1. authoritative metadata in target locale
2. another trustworthy provider in target locale
3. authoritative/original source language
4. professional automatic translation to target locale
```

Fields may include:

- overview/description;
- tagline;
- episode descriptions;
- chapter titles/descriptions;
- extras descriptions;
- collection descriptions;
- selected taxonomy labels where a canonical localisation is not already defined.

Titles must not be blindly translated when an official localised title exists. Generated titles are a last-resort fallback only.

## 7. Translation provenance

Never overwrite the source text irreversibly.

Each translated field records:

```text
source_text/version
source_locale
target_locale
provider/model
provider_version if available
translated_at
glossary_version
translation_memory_hit
quality/confidence signals
provenance source
status: machine_translated | official | user_override
```

When better official metadata later appears, it may become preferred without deleting the historical translation/audit trail.

## 8. Translation provider abstraction

MediaForge does not hard-code one vendor.

```text
TranslationProvider
├── LocalModelProvider
├── ProfessionalCloudProvider
├── LLMTranslationProvider
└── PluginTranslationProvider
```

Supported policy modes:

- Local only;
- Professional cloud only;
- Hybrid;
- Disabled.

Cloud providers are optional. Core MediaForge remains usable without paid translation APIs.

## 9. Glossary and translation memory

A versioned glossary protects names and technical terminology. Translation memory reduces cost, latency and wording drift.

Translation-memory identity should include at least:

```text
source locale
target locale
normalised source text
semantic context/domain
glossary version
```

Do not reuse context-sensitive translations blindly across unrelated domains.

## 10. Background jobs

Translation is normally asynchronous and cached, not performed on every page render.

```text
metadata imported/updated
 -> missing target-locale fields detected
 -> translation job queued
 -> quality checks
 -> result/version stored
 -> UI receives update event
```

Batch processing must support pause/resume, rate limits, provider quotas and cost budgets.

## 11. Privacy and cost controls

Before sending metadata externally, MediaForge applies provider policy and privacy classification.

Settings include:

- allowed providers;
- monthly/absolute cost budget;
- batch limits;
- local-only libraries;
- Adult/private metadata external-provider permission;
- opt-out per library/field.

Adult/private content must not be sent to a cloud translation provider unless the user has explicitly enabled a provider whose terms/policies allow that content.

## 12. Search and sorting

Search must handle locale-specific normalisation without destroying canonical text. Consider accents, umlauts, articles and alternate localised titles. Locale-aware display sorting may differ from canonical identity/matching.

## 13. Release quality gate

For first-class launch locales, release QA requires:

- 100% required UI-key coverage;
- no unexpected fallback/English leakage in normal flows;
- locale-aware plural/date/number tests;
- screenshot/layout smoke tests for expansion/overflow;
- glossary checks;
- representative human-quality review of critical flows;
- all upstream statuses mapped through canonical MediaForge states.

Later community/extended locales may use a lower completeness tier until promoted to first-class.
