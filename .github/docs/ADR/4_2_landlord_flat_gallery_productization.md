# ADR 006: Landlord Flat Gallery Productization

- **Status:** Accepted
- **Date:** 2026-05-02
- **Scope:** Step 4.2 landlord and escort gallery MVP surface on top of the frozen Step 4.1 backend contract

## Context

Step 4.1 froze the backend contract for flats, photos, votes, protected media delivery, and bilateral reporting. What it did not settle was the first product-shaped mobile surface that landlords and escorts would actually use.

Step 4.2 is worth its own ADR because it locks in product decisions that are broader than implementation detail:

1. The mobile app now exposes the first real landlord and escort workflow rather than only a security demonstrator.
2. Landlord and escort roles share the same gallery domain but do not share the same visible actions, moderation tools, or state semantics.
3. The product must preserve anonymity boundaries even while showing useful aggregate or personal state.
4. The gallery depends on memory-scoped data handling and protected backend media delivery, which is a product constraint as much as a technical one.
5. Step 4.2 closes as an MVP slice while deliberately deferring richer media UX and release-style cache proof.

## Decision

Step 4.2 treats the flat gallery as the first product-facing landlord and escort surface with the following requirements.

### Product Surface Boundary

- The gallery is the first credible landlord and escort product surface in the mobile app.
- It must feel like a real workflow for viewing flats, managing owner media, and performing role-specific moderation actions.
- It must not regress into a manual role-toggle demo or a generic backend probe screen.

### Role-Derived Behavior

- The active role is derived from stored secure session metadata returned by landlord bootstrap or escort OTP verification.
- Landlord mode exposes:
  - flat creation
  - flat contact metadata
  - photo upload
  - photo deletion
  - landlord-side escort reporting
- Escort mode exposes:
  - gallery viewing
  - vote submission
  - escort-side landlord reporting
- Owner-only actions must remain hidden for escorts, and escort-only actions must remain hidden for landlords.

### Owner Product Contract

- Flat creation requires `title`, `description`, `contact.phone`, and `contact.email`.
- WhatsApp, Telegram, and Viber shortcuts remain optional.
- Owner media management happens inside the gallery surface rather than through a separate technical demo block.
- Uploaded photos should appear back in the same gallery flow immediately after success.

### Guest Product Contract

- Escorts consume the same gallery domain through a restricted guest surface.
- The guest surface shows protected gallery media, listing contact information, aggregate vote state, and the escort's own submission state.
- The guest surface must never expose owner-only write controls.

### Rendering And Data Handling

- Gallery data is fetched through the signed Step 3 and Step 4.1 request path.
- Query state is kept in RAM through React Query without adding disk persistence.
- Gallery cards render through `FlashList` as the Step 4.2 list implementation target.
- Protected images use memory-only caching through `expo-image` where that component is active.
- The product avoids introducing app-managed persistent storage for gallery JSON or gallery media.

### Moderation And Anonymity Boundary

- Votes and reports are anonymous from the counterpart's perspective.
- The product may show aggregate vote counts and the current actor's own action state.
- The product must not reveal:
  - voter identity
  - reporter identity
  - per-counterparty moderation history to the reported side
- Any future surface that reveals reporter identity belongs in a separate admin or moderator website, not in the mobile app.

### MVP Closure Boundary

- Step 4.2 is considered closed for MVP once the following are validated on device:
  - landlord role detection
  - flat creation
  - photo upload
  - photo deletion
  - escort OTP activation into guest mode
  - escort vote submission
  - escort landlord-report submission
  - `FlashList` gallery rendering
- The following are explicitly deferred beyond Step 4.2 MVP closure:
  - carousel browsing
  - pinch-to-zoom or richer image viewing
  - release-style offline and cache verification after full app close

## Consequences

### Positive

- The mobile app now has a documented first MVP-grade landlord and escort workflow.
- Role-specific product behavior is frozen at the product level rather than being inferred from UI code.
- The anonymity model is recorded as an explicit product rule.
- Step 4.2 closure now has a clear line between MVP requirements and later polish.

### Tradeoffs

- The current media experience is intentionally functional rather than fully polished.
- Cache handling is configured conservatively, but release-style proof remains deferred and must still be verified later.
- The product is now committed to secure-session-derived role behavior instead of any fallback manual switching in the real surface.

## Validation

Step 4.2 is considered validated across three layers:

1. Focused backend and mobile tests covering the flat contract, session metadata handling, signed flat actions, and escort OTP client behavior.
2. TypeScript validation confirming the `FlashList` gallery integration compiles cleanly in the mobile app.
3. Manual device verification showing:
   - landlord flat creation works
   - landlord photo upload works
   - landlord photo deletion works
   - landlord escort reporting works
   - escort OTP verification succeeds and invalid OTP is rejected
   - escort session activation flips the gallery into guest mode
   - escort voting works
   - escort landlord reporting works

## Future Development Carry-Forwards

- Preserve role-derived product behavior from stored secure session metadata.
- Keep counterpart anonymity rules intact unless a later ADR deliberately changes them.
- Treat richer media UX as follow-up product work, not as missing Step 4.2 correctness.
- Run release-style cache and offline verification in a later hardening pass before using cache guarantees as a stronger product claim.
