## Summary

<!-- What does this change and why? Link the issue / spec section. -->

## Type

- [ ] feat
- [ ] fix
- [ ] refactor
- [ ] docs
- [ ] test
- [ ] chore

## Checklist

- [ ] Follows the spec in `docs/MediaForge/` (spec updated if behaviour changed)
- [ ] `make ci` passes (Pint, PHPStan max, Pest)
- [ ] Frontend checks pass (`npm run build`, `npm run type-check`) if UI touched
- [ ] New writes go through an Action (audited); no business logic in controllers/components
- [ ] New module boundaries covered by architecture tests
- [ ] Conventional Commit title
- [ ] ADR added for cross-module / rule-breaking changes
