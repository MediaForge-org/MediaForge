# Security Policy

## Reporting a vulnerability

Please report security vulnerabilities **privately**. Do not open a public issue.

- Use GitHub's [private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability)
  ("Report a vulnerability" in the Security tab), or
- email the maintainers (see the repository's contact info).

Include: affected version, a description, reproduction steps, and impact. We aim
to acknowledge within a few days and to coordinate a fix and disclosure timeline
with you.

## Supported versions

Until a stable 1.0 release, only the latest `main` and the most recent tagged
release receive security fixes.

## Scope & hardening notes

MediaForge is designed to run in a home network behind a reverse proxy. Security
architecture (threat model, defense layers, secrets handling) is documented in
`docs/MediaForge/architecture/security.md`. Notable defaults:

- The app binds to localhost only; TLS termination is the operator's reverse proxy.
- Media libraries are mounted read-only (originals are never modified).
- Connector secrets are encrypted at rest and never logged (recorder denylist).
- Argon2id password hashing; roles + policies gate every management action.
