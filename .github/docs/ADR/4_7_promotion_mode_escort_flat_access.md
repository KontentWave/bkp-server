# ADR 011: Promotion-Mode Escort Flat Access Override

- **Status:** Accepted
- **Date:** 2026-06-09
- **Scope:** Temporary production override for escort flat-gallery visibility during promotion and early market seeding

## Context

The original Step 4.1 flat-gallery contract intentionally kept escort visibility narrow:

1. escorts could list only flats reachable through an accepted invitation relationship,
2. escort photo access followed the same flat-level permission rule,
3. voting and escort-side landlord reporting depended on that same scoped flat visibility.

That strict rule is still the correct long-term product contract because it avoids accidentally turning the gallery into a public directory.

During the current promotion phase, however, the product has a different practical constraint: newly onboarded escorts need to see real flats immediately even when no landlord has yet created an accepted invitation link for that escort phone number.

Without an explicit temporary override, the product produces a confusing pilot experience:

- the mobile app can show that escort device access is verified,
- but the flat gallery returns zero listings,
- and the empty state implies that a landlord must share access first.

That behavior is technically consistent with the original contract, but it is counterproductive while the marketplace is still being seeded.

## Decision

### Default Contract Rule

- The accepted-invitation escort access rule remains the default backend contract.
- `FlatGalleryController::escortAccessibleFlatsQuery()` continues to enforce that rule whenever the override is not explicitly disabled.
- The strict Step 4.1 ADR remains the baseline architecture decision.

### Promotion Override Rule

- The backend may temporarily disable escort invitation gating with:

```env
FLAT_GALLERY_ENFORCE_ESCORT_INVITATION_ACCESS=false
```

- When this flag is `false`, authenticated escort sessions may list all flats through `GET /api/flats`.
- Escort access to protected flat photos follows that broader flat visibility because photo authorization already delegates to the same flat-access rule.
- Escort voting and escort-side landlord reporting also follow that broader visibility because they operate on the flats returned through the same gallery contract.

### Operational Ownership Rule

- This override is an operational rollout switch, not a new permanent product contract.
- Production may enable it during promotion and disable it again once invitation-linked access becomes commercially realistic.
- Local development may also use it to simplify escort rehearsals.

### Documentation Rule

- Operator-facing documentation must make this switch explicit in `.env.example`, the project sheet, and the relevant ADR set.
- The multilingual product work and the promotion-mode access override should be tracked and committed separately because they address different risks:
  - multilingual work changes client-facing UX and data presentation,
  - promotion override changes who can see the flat inventory.

## Consequences

### Positive

- Newly verified escorts can see real flats immediately during promotion.
- Pilot feedback is easier to collect because escorts no longer depend on landlord-by-landlord invitation seeding.
- The override uses an existing backend decision point instead of introducing a second ad hoc code path.

### Tradeoffs

- Production behavior can temporarily diverge from the original strict Step 4.1 contract.
- The current escort empty-state copy may become misleading while promotion mode is enabled because it still explains the invitation-linked model.
- Broader escort visibility increases exposure of flat inventory during the promotion window.

## Rollout Guidance

- To enable promotion mode in production:

```env
FLAT_GALLERY_ENFORCE_ESCORT_INVITATION_ACCESS=false
```

- After changing the value on a deployed Laravel instance, refresh config cache:

```sh
php artisan optimize:clear
php artisan config:cache
```

- Verify the effective value with:

```sh
php artisan tinker --execute="dump(config('services.flat_gallery.enforce_escort_invitation_access'));"
```

- Re-enable the strict contract later by setting the flag back to `true`.

## Future Development Carry-Forwards

- Keep the strict accepted-invitation model as the long-term default contract unless a later ADR deliberately replaces it.
- If promotion mode stays enabled for an extended period, update the escort empty-state copy so it no longer implies invitation-linked access is currently required.
- Track promotion-mode rollout changes separately from multilingual or other UX-only changes so deployment and rollback remain easy to reason about.
