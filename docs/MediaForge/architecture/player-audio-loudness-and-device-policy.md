# MediaForge player audio, loudness and device audio policy

**Status:** binding target architecture

## Purpose

MediaForge owns the visible player and audio-control experience even when playback is ultimately served by a Jellyfin-derived video engine, an Audiobookshelf-derived audio engine or a platform-native client. Upstream engines expose capabilities; MediaForge defines the canonical user-facing policy, settings and UX.

The primary goals are:

- make quiet media practically usable without forcing the operating system or amplifier to compensate;
- keep a simple VLC-like volume control for normal users;
- offer high-quality advanced audio controls without turning every playback session into a manual mastering exercise;
- prevent avoidable clipping and preserve direct-play whenever the selected client/engine can apply the requested processing locally;
- keep behavior consistent across web, desktop, mobile and TV clients while respecting device capabilities.

## Simple volume contract

The normal player exposes a single volume slider whose allowed maximum is user-configurable:

- 100% maximum;
- 150% maximum; or
- 200% maximum.

`100%` is unity gain. Values above 100% are an intentional playback boost, not a second operating-system volume control.

The canonical simple-mode mapping is VLC-like:

- `100%` = unity / 0 dB nominal player gain;
- `150%` = boosted playback according to the canonical gain curve;
- `200%` = approximately 2x linear amplitude, i.e. approximately `+6.02 dB` nominal gain before protection/normalization stages.

Implementations may use a perceptually smoother slider curve, but the 100% and 200% anchors must remain stable and testable.

The UI must visibly distinguish boosted playback from ordinary 0–100% volume. It must never silently claim 150/200% support when the current client/engine cannot provide the requested gain path.

## Protection and signal chain

Boosted playback must not be implemented as blind sample multiplication that clips.

The canonical processing order is conceptually:

1. source decode;
2. channel mapping;
3. optional loudness-normalization gain;
4. optional dialogue/centre-channel treatment;
5. optional dynamic-range compression;
6. optional user EQ / tone controls;
7. simple volume boost above unity and optional advanced preamp;
8. true-peak-aware limiter / clipping protection;
9. final device/client volume handoff.

Exact DSP placement may differ where an engine/library requires it, but effective behavior must be deterministic and documented.

Default clipping protection is enabled whenever effective gain can exceed unity. Advanced users may disable processing stages, but MediaForge must expose the resulting clipping risk rather than pretending the signal is protected.

## Loudness normalization

MediaForge supports optional loudness normalization based on measured audio rather than a fixed blind gain.

Canonical analysis data may include, where technically available:

- integrated loudness (LUFS);
- true peak (dBTP);
- loudness range (LRA);
- channel layout and channel count;
- optional short-term/momentary summary data when useful for analysis, not as high-volume database noise;
- analysis algorithm/tool version and provenance.

The loudness target is configurable and can be supplied by presets. MediaForge must not hard-code one target for every media type and device. Movies/series, music and spoken-word/audiobook playback may use different profile defaults.

Analysis is cached and versioned. Replaying a file must not require re-running loudness analysis when a compatible result already exists.

## Dialogue, surround and dynamic range

Advanced player audio supports:

- dialogue boost / voice clarity;
- centre-channel boost where a real centre channel exists;
- deterministic multichannel-to-stereo downmix policies;
- dynamic-range compression with at least Off, Light, Medium, Strong and Night presets;
- a Night mode intended to reduce large dialogue-to-effects level differences;
- preservation of the original multichannel path when processing is disabled and the client can direct-play it.

MediaForge must not invent a discrete centre channel for stereo sources. If voice enhancement is available for stereo, it is exposed as a separate capability/profile rather than mislabeled as centre-channel gain.

## Equalizer and advanced controls

Advanced mode may expose:

- preamp in dB;
- EQ presets and custom EQ bands;
- simple bass/treble controls as a convenience surface over the canonical EQ model;
- loudness target;
- limiter enable/ceiling;
- dialogue/voice boost;
- DRC/Night mode;
- channel/downmix policy.

The advanced preamp is separate from the 0–200% simple slider. Combining them must produce one computed effective-gain plan with bounded validation and limiter behavior; it must not create two unrelated gain stages whose interaction is unknowable.

## Scope and precedence

Audio settings may exist at multiple scopes. The canonical precedence is most-specific wins, with explicit inheritance:

1. temporary playback-session override;
2. item/edition override, if the user explicitly saves one;
3. library/media-type profile;
4. device profile;
5. user default;
6. MediaForge default.

A device profile may describe TV, browser, desktop speakers, headphones, AVR or other output contexts. Unsupported settings remain stored as user intent where appropriate but are not falsely reported as active on a device that cannot implement them.

## Audiobook / spoken-word policy

Audiobooks use the same canonical audio-control model but may have spoken-word-oriented defaults.

In particular:

- voice clarity and consistent perceived loudness matter more than preserving cinema-style dynamic range;
- audiobook profiles are not copied blindly from movie/TV profiles;
- chapter boundaries, playback speed, bookmarks and narration progress remain independent of loudness processing;
- loudness processing must not change canonical chapter timestamps or source identity.

## Engine and client responsibilities

MediaForge API/contracts describe user intent and effective audio policy using MediaForge concepts, never Jellyfin/Audiobookshelf-specific IDs.

The playback gateway and engine adapters negotiate capabilities such as:

- client-side gain/limiter support;
- server/engine-side audio-filter support;
- loudness-analysis availability;
- channel-layout/downmix support;
- EQ/DRC/dialogue-processing support;
- direct-play constraints.

Prefer local/client DSP when it provides the requested result without forcing a transcode. If processing requires a server/engine audio transform, the system may select an audio-transcode path while keeping large media bytes out of Laravel. Unsupported processing must degrade explicitly to a known subset, not silently produce a different sound.

The Jellyfin-derived and Audiobookshelf-derived engines remain internal specialists. Their native UI settings are not the primary product contract; MediaForge settings are.

## Native MediaTools responsibility

The Rust/native MediaTools layer may provide performance-sensitive reusable primitives around FFmpeg or other mature libraries for:

- loudness / EBU-R128 analysis;
- true-peak analysis;
- deterministic filter-graph construction;
- limiter/normalization helpers;
- channel-layout inspection and downmix helpers;
- validation of effective gain/filter plans.

It must not own user preferences or redefine the canonical playback-domain model.

## Canonical contract capability

Exact generated schemas are defined in the contract tracks, but the canonical model must be able to represent at least:

- `volume_percent`;
- `max_volume_percent` (`100`, `150`, `200`);
- optional `preamp_db`;
- loudness normalization enabled/disabled and optional target LUFS;
- limiter enabled/disabled and optional true-peak ceiling;
- dialogue/voice boost mode or bounded amount;
- DRC mode;
- EQ preset/custom-band representation;
- channel/downmix policy;
- profile scope and inheritance source;
- requested state and effective state;
- unsupported/degraded capability reporting.

Do not persist rapidly changing slider telemetry as high-volume audit/database rows. Persist durable preferences/profiles and meaningful playback/session state only.

## UX requirements

### Simple mode

- one obvious 0–100/150/200% volume slider according to the user's configured maximum;
- 100% clearly marked as normal/unity;
- boosted region visually distinguishable;
- mute and normal keyboard/remote accessibility;
- no requirement to understand LUFS or dB.

### Advanced mode

- preamp/gain;
- normalization and target;
- limiter/protection;
- dialogue/voice boost;
- DRC/Night mode;
- EQ/tone controls;
- channel/downmix settings;
- profile scope/device selection where relevant.

The UI must remain usable with remote-control/TV focus navigation as well as mouse, keyboard and touch.

## Validation and safety invariants

- Reject out-of-range volume/gain/limiter values; never silently clamp contradictory persisted contracts without surfacing the effective value.
- `volume_percent > 100` is invalid when the active maximum is 100.
- `volume_percent > 150` is invalid when the active maximum is 150.
- A client/engine that cannot apply a requested feature reports it as unsupported/degraded rather than active.
- Analysis provenance/version is retained so stale measurements can be invalidated deterministically.
- Processing must not modify source files merely to make playback louder.
- Originals remain unchanged; playback DSP and reconstructed/restored audio editions are separate concepts.
- Audio enhancement/restoration jobs in Track 25 must never be confused with temporary playback gain/normalization.

## Required verification

Before the playback track is considered complete, tests must cover at least:

- 100% unity behavior;
- configured 100/150/200% limits and 200% nominal boost semantics;
- boosted playback with limiter/protection enabled;
- invalid/contradictory gain settings;
- loudness-analysis reuse/version invalidation;
- profile precedence and per-device fallback;
- dialogue/DRC/EQ capability negotiation;
- multichannel direct-play when processing is disabled;
- explicit degraded/unsupported states;
- web/client accessibility for the simple volume control;
- TV remote navigation;
- audiobook-specific profile behavior distinct from movie/TV defaults.
