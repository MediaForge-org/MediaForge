# External Services, Managed Upstreams and the MediaForge Product Surface

Status: **binding target architecture**
Updated: **2026-09-27**
Supersedes the fork/source-baseline model previously described in this document.

## 1. Product rule

MediaForge owns the normal product surface.

Users interact with MediaForge concepts. Specialist programs provide capabilities behind adapters.

Their native UIs may remain available for administration, diagnostics or fallback; they are not the
normal MediaForge workflow.

## 2. External specialist services

### Jellyfin

Preferred specialist server for:

- video playback;
- streaming/transcoding;
- subtitles/audio tracks;
- technical media information;
- sessions/device capabilities;
- watch progress/runtime state.

MediaForge integrates through supported Jellyfin APIs.

No copied Jellyfin source tree is required.

### Audiobookshelf

Preferred specialist server for:

- audiobook/podcast playback;
- chapter/audio-specific runtime behavior;
- listening progress/runtime state;
- supported ebook functionality where useful.

MediaForge integrates through supported Audiobookshelf APIs.

No copied Audiobookshelf source tree is required.

### Scene Tracker

Scene Tracker is a separate metadata/community product.

It may provide:

- scenes;
- performers;
- studios;
- tags/taxonomy;
- source provenance;
- matching/discovery/community data.

It has its own database and versioned API.

MediaForge never queries Scene Tracker PostgreSQL directly.

### Stash

Stash is no longer a required internal MediaForge engine.

A future Stash adapter may be supported if it provides useful external capabilities, but that does
not make a deep fork the default architecture.

## 3. Managed acquisition services

Remain upstream-managed by default:

- NZBGet;
- qBittorrent;
- Prowlarr;
- optional/transitional Sonarr/Radarr/Whisparr.

MediaForge owns the normal UX, policy, provenance, queue aggregation and compatibility layer.

## 4. No upstream source-baseline import requirement

Track 02 must not import Jellyfin/Stash/Audiobookshelf source trees merely because the old plan said so.

Instead prepare:

- adapter contracts;
- compatibility/version metadata;
- fixtures;
- optional Compose manifests/images;
- connection/setup flows.

A source fork requires a later evidence-backed ADR.

## 5. Compatibility model

For each integration record:

- component key;
- detected version;
- supported version range;
- capability set;
- health/compatibility status;
- API contract/fixture version;
- last compatibility verification.

## 6. MediaForge-only normal UI

Normal pages remain MediaForge-authored.

The frontend never becomes a launcher or a pile of embedded upstream dashboards.

## 7. Native UI fallback

Admin/debug only.

Never leak tokens/credentials.
Do not make iframe/native UI the primary integration.

## 8. Resilience

Catalog browsing is PostgreSQL-backed and survives temporary upstream outages.

Only service-dependent actions degrade.

## 9. NZBGet decision

NZBGet remains the sole target managed Usenet backend unless a later ADR demonstrates a hard
missing requirement.

Its native UI remains an advanced/admin fallback; MediaForge owns normal download UX.

## 10. Upstream update policy

```text
new upstream version
 -> compatibility fixture/test
 -> capability/auth/health validation
 -> representative integration smoke test
 -> supported-range update
 -> optional bundled-image pin update
 -> rollback path retained
```

Upstream source merging is not part of the normal process.
