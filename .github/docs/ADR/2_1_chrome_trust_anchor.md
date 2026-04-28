# ADR 002: Chrome Trust Anchor

- **Status:** Accepted
- **Date:** 2026-04-25
- **Scope:** Step 2.1 local development baseline

## Context

Step 2.1 establishes the first landlord-side trust anchor for desktop workflows. The goal is to let a verified landlord highlight a phone number on `amaterky.sk` and trigger the BKP backend invitation flow without requiring the future React Native app or hardware-backed request signing stack.

The roadmap defined four practical requirements for this step:

1. A standalone Chrome extension that can be loaded in Developer Mode.
2. A popup flow for storing backend connection details and a landlord bearer token.
3. A context-menu-driven invitation action restricted to `amaterky.sk` selections.
4. A safe bridge into the backend invitation flow, even though the main `/api/invitations` route is protected by `hardware.signature`.

During implementation, two additional constraints became explicit:

1. The Chrome extension and the future iOS share extension must remain physically and operationally separate.
2. The browser workflow needs a dedicated backend endpoint because Chrome cannot satisfy the native hardware-signing requirements used by the protected mobile-client routes.

## Decision

The local Step 2.1 implementation is accepted with the following architectural decisions.

### Packaging And Workspace Boundaries

- Use a standalone Chrome Manifest V3 extension in `bkp-client/tools/chrome-trust-anchor/`.
- Keep the iOS trust-anchor placeholder in `bkp-client/apps/mobile-app/extensions/ios-share-extension/` only as a reserved location, not as a shared runtime surface.
- Treat the Chrome extension as a separate deliverable with its own permissions, storage, testing, and release flow.

### Browser Authentication Model

- Use a landlord Sanctum bearer token as the browser-safe authentication bridge.
- Store the backend base URL and Sanctum token in `chrome.storage.local`.
- Keep this token-based browser flow limited to the dedicated browser-safe invitation route rather than attempting to reuse the hardware-signed mobile trust model.

### Invitation Triggering Model

- Register a context-menu action for text selections on `*://*.amaterky.sk/*` only.
- Sanitize highlighted text through a shared phone utility before any network request is sent.
- Provide a popup-level `Send test invite` action so the browser-safe route can be verified without depending on page-selection behavior.

### Backend Integration Contract

- Send browser-created invite requests to `POST /api/browser/invitations`.
- Include the stored Sanctum token in the `Authorization: Bearer` header.
- Keep the main `POST /api/invitations` route reserved for clients that satisfy `hardware.signature`.

### User Feedback Model

- Surface invite results through both the extension badge and the popup status area.
- Use transient success feedback rather than persistent success state so the extension returns to a neutral state after local confirmation.
- Preserve explicit error feedback when a request fails or the extension is not configured.

## Consequences

### Positive

- Landlords can exercise the invitation flow from a desktop browser before the native mobile clients exist.
- The Chrome extension remains narrowly scoped to the target site and does not require broader browser permissions.
- The browser-safe route avoids weakening the main hardware-signed invitation path.
- Step 2.1 can be validated independently of the future iOS share extension and React Native app.

### Tradeoffs

- The browser trust anchor is weaker than the native hardware-backed trust model because it relies on a stored bearer token.
- `chrome.storage.local` is practical for local development, but it is not equivalent to Secure Enclave or KeyMint-backed storage.
- Real-world validation against live target-site data and operational landlord workflows is still pending.

## Validation

Step 2.1 was validated with three layers of checks:

1. Focused Node tests covering phone-number sanitization.
2. Manual popup verification that the backend URL and Sanctum token can be stored and reused.
3. Live local invitation verification against the running Laravel server using `POST /api/browser/invitations`, including successful authenticated request creation and backend-side invitation persistence.

## Operational Notes

- Step 2.1 is complete for local development, not for production rollout.
- Manual Chrome Developer Mode loading is still the intended distribution model for this step.
- Local invite validation still depends on the Laravel backend, queue, and any external SMS configuration required for the final downstream delivery path.

## Future Development Carry-Forwards

- Replace the temporary bearer-token bootstrap flow with a stronger landlord enrollment path before production use.
- Revisit browser token lifecycle, revocation, and rotation before any non-local deployment.
- Decide whether the desktop trust anchor remains a long-term product surface or becomes only a transitional operator tool once the native landlord app is available.
- Keep the iOS share extension architecture separate even if Chrome and iOS continue to share backend invitation semantics.
