# Contributing to MediaForge

Thanks for your interest in MediaForge — an open-source local media enhancement
suite for Jellyfin & Audiobookshelf. This guide gets you productive quickly.

## The specification is the source of truth

`docs/MediaForge/` is the authoritative specification. **If code and docs
disagree, one of them is a bug.** Behaviour changes update the spec *first*
(the docs change is part of the PR). Start with:

1. `docs/MediaForge/MediaForge_Master_Engineering.md` — the eleven architecture rules
2. `docs/MediaForge/architecture/overview.md` — module cut, Action/Job/Event contracts
3. `docs/MediaForge/database/core-schema.md` — schema conventions
4. `docs/MediaForge/developer-handbook/coding-standards.md`

## Development setup

Requirements: Docker + Docker Compose, Make. Then:

```bash
make setup     # clone → running system in < 15 min
make test      # Pest (arch + unit + feature)
make ci        # style + static analysis + tests (the merge gate)
```

The app runs at http://localhost:8100. See [README.md](README.md) for the full
port map and connector setup.

## Coding standards (enforced in CI)

- PHP 8.4, `declare(strict_types=1)` everywhere. **Pint** (Laravel preset) and
  **PHPStan at max level** must be clean.
- **Pest** for tests, incl. architecture boundary tests. PostgreSQL only — no SQLite.
- Layering: Controller → Action (`AuditableAction`) → Service → Model. No business
  logic in controllers or React components. DTOs are `final readonly`, not arrays.
- Module boundaries are test-enforced (`tests/Arch`). A module whose boundaries
  aren't testable is cut wrong.
- React: typed function components in `.tsx` files, with per-page prop interfaces.
  Base components only in `resources/js/components/base/`.

## Commits & pull requests

- **Conventional Commits** (`feat(connectors): …`, `fix(auth): …`) — release notes
  generate from them.
- Keep PRs focused. Fill in the PR template. CI (`make ci` + frontend checks) must pass.
- Cross-module or rule-breaking changes need an ADR under `docs/MediaForge/adr/` first.

## Reporting bugs & requesting features

Use the GitHub issue templates. For security issues, follow [SECURITY.md](SECURITY.md)
— do **not** open a public issue.

By contributing you agree your contributions are licensed under the project's
[AGPL-3.0-or-later](LICENSE) license.
