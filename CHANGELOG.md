# Changelog

All notable changes to MediaForge are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[Semantic Versioning](https://semver.org/). Entries are generated from
[Conventional Commits](https://www.conventionalcommits.org/).

## [Unreleased]

### Added — V1 foundation (in progress)

- Laravel 12 / Vue 3 + Inertia + TypeScript + Tailwind v4 application skeleton.
- Modular-monolith structure (`app/{Core,Modules,Connectors,Http}`) with
  architecture boundary tests.
- Docker: multi-stage production image, dev + production Compose stacks, Makefile.
- 12-factor environment configuration for the future official Docker image.
