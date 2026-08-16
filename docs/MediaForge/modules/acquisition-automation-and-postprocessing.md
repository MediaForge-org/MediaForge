# Acquisition Automation, Naming and Post-Processing

Status: **binding long-term feature specification**  
Updated: 2026-08-17

This document extends `acquisition-center.md` with automated source search, managed automation backends, deterministic naming, torrent-safe imports, disc processing and transcoding.

## 1. AcquisitionBlueprint

Every automated acquisition may create an explicit plan before the first byte is downloaded.

```text
AcquisitionBlueprint
├── id
├── requested_media/work/edition
├── intake_source
├── expected_media_kind
├── expected_release_identity
├── release_score/evidence
├── canonical_folder_template
├── canonical_file_template
├── downloader_backend
├── downloader_category/tags
├── secret_reference nullable
├── torrent_seed_policy nullable
├── disc_policy nullable
├── remux_profile nullable
├── transcode_profiles[]
├── final_library
└── approval/automation policy
```

The blueprint is revisioned/audited. It is not permission to bypass access controls or acquire content the user is not entitled to obtain.

## 2. Drag/drop and manual intake

Accepted intake includes configured/legitimate inputs such as:

- `.nzb`;
- `.torrent`;
- magnet link;
- local file/folder/disc image;
- result from configured Newznab/Torznab/native provider;
- Browser Companion payload selected by the user.

Classification uses multiple signals:

```text
provider category
release/job name
package file list
known catalogue identities
MediaInfo/ffprobe after download
optional AI only when deterministic parsing is insufficient
```

Possible target kinds include Movie, Series/Episode/Season, Audiobook, Adult, Music and Unknown/Review.

Ambiguous classification goes to Review rather than an unsafe automatic library move.

## 3. Unified source/provider layer

Do not hard-code a small tracker/indexer whitelist. Support capabilities:

```text
Newznab
Torznab
Prowlarr-managed definitions
Jackett-compatible Torznab endpoints
native provider plugins
RSS/search feeds
Browser Companion
manual intake
```

Provider capabilities are declared rather than inferred from names:

```text
search
categories
freeleech
seeders/leechers
age/retention
RSS
NZB download
torrent download
magnet
authentication mode
browser fallback
```

## 4. Prowlarr and automation backends

Prowlarr is an optional managed backend for maintaining broad indexer/tracker integrations.

Sonarr, Radarr and Whisparr are optional transitional automation backends for series, movies and Adult respectively. MediaForge presents their capabilities through MediaForge UX and canonical data. Their native UIs remain advanced/admin fallbacks.

## 5. Unified search and Release Decision Engine

Search all enabled providers and normalise results to one model. The Release Decision Engine scores candidates against user profiles and current library state.

Signals can include:

- canonical title/episode match;
- source type (UHD Blu-ray/Blu-ray/WEB/etc.);
- resolution;
- codec;
- bit depth;
- HDR/Dolby Vision;
- audio languages/formats/channels;
- subtitles;
- release group;
- size range;
- age/retention/availability;
- seeders/freeleech for torrents;
- duplicate/current-edition comparison;
- provider reliability history.

The score must be explainable. A high score is not permission to override an ambiguous identity match.

## 6. Wanted, monitoring and upgrades

MediaForge provides one product-level Wanted model:

```text
WantedItem
├── media/work target
├── monitoring policy
├── minimum profile
├── preferred profile
├── upgrade_until
├── source preferences
├── auto-download policy
└── last search/results
```

Modes:

- notify only;
- approval required;
- automatic exact-match acquisition.

Avoid duplicate downloads and avoid downgrades unless the user explicitly requests an alternate edition.

## 7. Usenet naming policy

SABnzbd handles download/verification/repair/unpack. MediaForge is the final naming authority.

For a normal single-media Usenet job, the default policy may be:

```text
final folder = sanitised download/NZB job name
final main media file = same base name + actual extension
```

Any temporary password marker or secret is removed from final names.

Store separately:

```text
source_name
download_job_name
canonical_output_name
secret_reference
```

Passwords/credentials are secrets, never provenance labels or final filenames.

## 8. Multi-file naming

Never blindly rename every file to the folder name.

Identify:

- main feature;
- episodes;
- extras;
- subtitle sidecars;
- NFO/metadata;
- samples/trailers.

For season packs, derive stable episode identity before applying the configured series naming template.

## 9. Torrent-safe import

The default torrent policy preserves the seed payload and creates a MediaForge library representation using the safest zero/low-copy mechanism:

```text
same filesystem -> hardlink preferred
data-capable CoW filesystem -> reflink where appropriate
otherwise -> copy
```

The library filename may differ from the qBittorrent payload path without breaking seeding.

Alternative advanced mode: rename through the qBittorrent API so qBittorrent remains aware of file/folder changes. Never rename active seed payload behind qBittorrent with an arbitrary filesystem move.

## 10. Per-tracker seeding policy

Policies may be scoped by tracker/provider:

```text
minimum ratio
minimum seed time
never auto-delete
upload limit/schedule
delete seed payload only after policy satisfied and library copy verified
```

The UI must make it impossible to confuse logical hardlinked library size with additional physical disk usage.

## 11. Failure/fallback orchestration

On a failed/incomplete acquisition, MediaForge may try a different candidate according to explicit policy:

```text
same release from another configured source
 -> alternate acceptable release
 -> optional Usenet/torrent fallback
 -> Review if ambiguity remains
```

Do not loop indefinitely. Record why each candidate failed and cap retries.

## 12. Quarantine and staging security

All completed downloads enter staging/quarantine before final import.

Inspect:

- actual MIME/container/type;
- archive contents;
- unexpected executable/script extensions;
- symlink/path traversal hazards;
- file-count/size anomalies;
- media-stream validity.

Original media libraries are not exposed as arbitrary downloader write targets.

## 13. Post-processing DAG

Model processing as resumable/idempotent stages rather than one opaque shell script:

```text
Acquire
 -> verify/repair/unpack
 -> probe
 -> identify
 -> disc analyse (if applicable)
 -> extract/remux (if selected)
 -> rename
 -> subtitle/audio policy
 -> transcode profiles (optional, parallel/queued)
 -> output verification
 -> move/hardlink/reflink/copy
 -> library registration/scan
 -> cleanup according to retention policy
```

Each node has state: pending/running/succeeded/failed/skipped/retryable, inputs/outputs, logs and provenance.

Custom user scripts remain supported as explicit DAG nodes with a documented environment/contract.

## 14. ISO/disc series processing

For user-owned/authorised disc images, MediaForge can analyse ISO/BDMV/VIDEO_TS and map titles/playlists to canonical media.

For a TV/series disc:

- detect episode-length titles/playlists;
- match episodes using exact/verified disc evidence;
- expose relevant extras separately;
- never guess ambiguous mappings;
- offer a review screen before irreversible writes.

Selected episodes/extras can be remuxed to Matroska with appropriate tooling. MKVToolNix is a remux/container tool, not the transcoder.

## 15. Codec/output profiles

Optional transcoding follows remux/extraction and creates derived editions/artifacts.

Minimum supported video profile families:

- H.264/AVC;
- H.265/HEVC;
- AV1.

Profiles declare:

```text
encoder implementation/hardware path
quality mode/value
resolution policy
bit depth
HDR/Dolby Vision policy
audio copy/transcode policy
subtitle policy
container
retention of source/remux
```

Users may select multiple outputs from one source. MediaForge understands them as derived editions/artifacts of the same work rather than unrelated duplicate media.

## 16. Storage forecast and resource scheduling

Before large operations estimate both final and peak temporary storage:

```text
download payload
unpack peak
remux output
parallel transcode outputs
cleanup/retention
```

The resource scheduler coordinates downloader bandwidth, torrent upload, playback, transcodes and optional AI workloads. Playback/user-interactive tasks have priority over background analysis.

## 17. Provenance

Track:

```text
source/provider
result/release identity
selection reason/score
automation backend if used
download backend
original names
post-processing DAG
rename operations
remux/transcode profiles
final file/edition
```

Never persist raw passwords, tracker session cookies or API secrets in provenance.

## 18. Browser Companion fallback

For sites without stable APIs, the browser companion may accept user-selected data from an already authenticated browser session and send the selected file/link/title metadata to MediaForge.

It must not automate bypass of CAPTCHA, access controls, paywalls, required forum participation or other anti-bot/security gates. Site-specific DOM helpers are optional plugins and must degrade gracefully when a site changes.
