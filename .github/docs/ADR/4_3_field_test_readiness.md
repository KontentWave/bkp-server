# ADR 007: Field Test Readiness

- **Status:** Accepted
- **Date:** 2026-05-03
- **Scope:** Step 4.3 rollout-readiness boundary for UI cleanup, Android parity, secure distribution, and closed-pilot gating

## Context

Step 4.2 closed the first landlord and escort gallery MVP surface, but that did not make the product ready for real-world deployment. The app still needs a dedicated readiness step between local validation and live field testing.

Step 4.3 deserves its own ADR because it freezes rollout order and operational gating rather than just implementation details:

1. The current mobile UI still contains developer-oriented framing that is acceptable for internal validation but not for real testers.
2. Android remains behind iOS on the secure-client boundary, so live cross-platform testing would otherwise mix product validation with platform incompleteness.
3. Screenshot blocking, APK distribution, and tester access control are rollout decisions, not incidental engineering details.
4. Real-user testing in this product domain involves sensitive phone numbers, reports, and private gallery data, so a closed pilot must be a deliberate gate rather than an informal next step.

## Decision

Step 4.3 is the rollout-readiness step that must complete before broad live testing begins.

### Execution Order

1. UI cleanup first
2. Android signer parity second
3. Android screenshot blocking third
4. APK distribution fourth
5. Closed-pilot checklist last

The work should not be treated as parallel rollout tasks. Each stage exists to reduce risk before the next stage starts.

### UI Cleanup Boundary

- Remove developer-facing diagnostics, transport language, and internal implementation framing from user-facing screens.
- Replace raw technical success and failure text with product-facing copy.
- Add clean loading and empty states so the app does not appear broken during network work or first-run setup.
- Treat the main landlord and escort entry flow as the first cleanup slice because it is the tester's first impression and currently exposes onboarding and invitation internals too directly.

### Android Parity Boundary

- Android must implement the same ADR 004 signer contract as iOS through a Kotlin KeyMint / Android Keystore local module.
- Laravel must be able to verify Android signatures using the same canonical payload rules and public-key format already validated on iOS.
- Android escorts should not be treated as trusted live-test actors until this parity is proven.

### Screenshot Blocking Boundary

- Android secure screens must enforce OS-level screenshot and recording blocking through the Android-capable screen-capture path.
- This protection should be validated only after the Android secure client is functional on the actual sensitive surfaces.

### Distribution Boundary

- Android testing distribution should use a sideloadable preview APK.
- Testers should receive a coherent build after UI cleanup and Android parity are complete, not an unstable developer-oriented snapshot.

### Closed-Pilot Boundary

- The first real-user rollout must be a small invited pilot, not a broad release.
- The pilot must define:
  - who can invite testers
  - who can revoke tester access
  - how compromised sessions or devices are disabled
  - how sensitive reports and urgent removals are handled
  - how rate limiting, log redaction, queue health, and incident response are monitored
- One end-to-end cross-platform rehearsal against the real pilot infrastructure is required before opening the pilot.

## Consequences

### Positive

- Live testing now has an explicit readiness gate instead of an informal judgment call.
- The roadmap distinguishes product MVP completion from rollout readiness.
- The first Step 4.3 implementation slice is clear: remove developer-facing noise from the mobile app before expanding scope.

### Tradeoffs

- Live deployment is deliberately slowed down in exchange for better operational control.
- Android work becomes a blocking rollout dependency rather than a later parity follow-up.
- Some internal convenience surfaces may need to move behind development-only tooling instead of staying visible in the main app.

## Validation

Step 4.3 should be treated as validated only when all of the following are true:

1. The cleaned mobile UI no longer exposes debug-oriented copy, raw transport framing, or internal diagnostic surfaces in the main tester path.
2. Android device onboarding completes with KeyMint-backed signing that Laravel accepts.
3. Android screenshot blocking is confirmed on sensitive screens.
4. The preview APK can be distributed and installed reliably by pilot testers.
5. A closed-pilot rehearsal succeeds end to end without security or operational regressions.

## Future Development Carry-Forwards

- Keep rollout gating explicit for future sensitive launches instead of assuming local MVP completion is enough.
- Preserve the closed-pilot model until operational behavior is proven stable.
- Treat any broader release as a later decision that requires its own readiness review.
