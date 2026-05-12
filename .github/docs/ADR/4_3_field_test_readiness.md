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

### Product Coverage Boundary

- Field testing targets two product contracts:
  - landlord
  - escort
- Each contract must be supported on two mobile platforms:
  - iOS
  - Android
- Step 4.3 should therefore be read as mobile parity and rollout readiness for both roles across both platforms, not as an Android-only escort expansion.

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

### Product Policy Boundary

- Step 4.3 also freezes the tester-facing product rules that were still fluid during Step 4.2 implementation.
- The mobile app should present a persistent first-run device mode choice for `landlord` or `escort` so non-technical testers are not dropped into an ambiguous mixed-role surface.
- This tester-facing device mode must remain separate from the verified secure session metadata. The stored actor type still controls authorization, while the saved device mode controls the onboarding and navigation shape shown on the device.
- OTP verification should reject a backend actor type that does not match the selected device mode rather than silently switching the device into the opposite tester path.
- The invitation flow should now be treated as a shared verified action available from both roles after authentication instead of as a landlord-only developer utility.
- Landlord and escort moderation visibility should remain asymmetric by policy:
  - landlords may see aggregate landlord-report counts and reasons on flats they own,
  - escorts may see only their own vote state and their own landlord-report state,
  - neither side may see which specific counterpart voted or reported through the mobile product surface.
- Landlord-side reported-escort summaries should be globally visible to participating landlords rather than limited to the landlord who originally filed the report. Once a landlord passes the participation gate, the product may reveal every recorded landlord-side escort report, including single-report incidents.
- Landlord-side reported-escort summaries should remain gated behind real landlord participation. For the current field-test contract, a landlord must publish at least one flat before the app reveals any landlord-side reported-escort summary data.
- Landlord escort reporting must support the field reality that the escorted person may be known only by a public ad identifier. The mobile and backend contract should therefore accept `amaterky.sk/<id>` style escort identities without requiring a pre-existing internal app account.
- Escort invitation and activation should ultimately be ad-bound and scraper-backed: the inviter supplies an ad id or ad URL, the backend scrapes the current phone from the ad during invitation, and installation validates again against the current ad phone before the device is bound.
- For the first strong implementation of this trust model, installation should require the ad to be in an active public state where the phone number is visible on the page.
- Support for the paid-but-hidden ad state such as `Vypnutý zadávateľom` is recognized as a necessary follow-up, but it may remain outside the first scraper-backed activation slice if that state does not expose the phone number publicly.
- The current external-ad-id moderation shortcut is an interim field-test contract, not the final escort identity model. After pilot validation, escort identity must expand to one stable internal subject with many historical public ad IDs and phone numbers.

### Android Parity Boundary

- Android must implement the same ADR 004 signer contract as iOS through a Kotlin KeyMint / Android Keystore local module.
- Laravel must be able to verify Android signatures using the same canonical payload rules and public-key format already validated on iOS.
- Android landlord and escort users should not be treated as trusted live-test actors until this parity is proven across onboarding and protected gallery actions.

### Screenshot Blocking Boundary

- Android secure screens must enforce OS-level screenshot and recording blocking through the Android-capable screen-capture path.
- This protection should be validated only after the Android secure client is functional on the actual sensitive surfaces.

### Distribution Boundary

- Android testing distribution should use a sideloadable preview APK.
- iOS testing distribution should use TestFlight for external pilot escorts rather than ad hoc file-host downloads.
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

### First Escort Pilot Plan

- The first escort pilot should stay intentionally narrow: one trusted escort on Android and one trusted escort on iOS.
- Each escort must have a live `amaterky.sk` ad in an active public state with a phone number visible on the page before invitation begins.
- Android escort distribution should use one hosted preview APK download link with simple sideload instructions.
- iOS escort distribution should use TestFlight, because a normal hosted file download is not a reliable installation path for external iPhone testers.
- Escort onboarding should be run one tester at a time with a landlord device controlled by the team, so invitation state, OTP delivery, and queue/provider behavior can be observed live.

### Escort Pilot Checklist

- Confirm the escort is a trusted invited tester and record which platform they use.
- Confirm the app build channel matches the platform plan:
  - Android: current preview APK
  - iOS: current TestFlight build
- Open the escort's `amaterky.sk` ad and confirm the page is active and the phone number is publicly visible.
- Confirm production operations are ready before sending the invite:
  - queue worker running
  - Vonage credentials present
  - sender configuration correct
  - Laravel config cache refreshed after any `.env` change
- Send the invitation from a landlord device controlled by the team.
- Watch the queue worker and confirm the notification job completes successfully.
- Confirm the escort receives the OTP and completes onboarding on the intended device.
- Confirm the newly onboarded escort can load the authenticated app surface without role mismatch.

### Minimal Tester Instructions

- Android escort tester:
  - open the hosted APK link
  - allow install from the provided source if Android prompts
  - install BKP and open it
  - choose `escort` mode when asked
  - wait for the SMS OTP after the landlord sends the invitation
  - enter the same phone number that appears on the live ad and then the OTP
- iOS escort tester:
  - install the BKP build through TestFlight
  - open BKP from TestFlight after installation
  - choose `escort` mode when asked
  - wait for the SMS OTP after the landlord sends the invitation
  - enter the same phone number that appears on the live ad and then the OTP
- Both testers should be told to contact the team immediately if:
  - the OTP does not arrive within a short window
  - the app reports a role mismatch or invalid OTP
  - the ad phone changed or the ad is no longer publicly visible

## Consequences

### Positive

- Live testing now has an explicit readiness gate instead of an informal judgment call.
- The roadmap distinguishes product MVP completion from rollout readiness.
- The first Step 4.3 implementation slice is clear: remove developer-facing noise from the mobile app before expanding scope.
- Testers now get a clearer role-separated first-run experience instead of inheriting internal mixed-role assumptions from development.
- The moderation model is clearer and more defensible because field-test users can report against real-world public escort identifiers without the app pretending every escort already exists as an internal record.

### Tradeoffs

- Live deployment is deliberately slowed down in exchange for better operational control.
- Android work becomes a blocking rollout dependency rather than a later parity follow-up.
- Some internal convenience surfaces may need to move behind development-only tooling instead of staying visible in the main app.
- OTP delivery now has an explicit provider-contingency risk: if Vonage account or deliverability issues persist, the rollout plan should allow a managed verification-provider swap such as Twilio Verify instead of treating the current SMS vendor as fixed.
- The tester-facing device mode introduces one more persisted client state that must be recoverable through an explicit reset path.
- Shared invitation capability across both roles increases product flexibility, but it also means pilot operations must be clear about who is allowed to invite whom during the first rollout.
- Deferring the escort identity redesign keeps the current pilot scope stable, but it also means the first live cycle will still rely on a simplified escort model that is weaker for long-term repeat-incident tracking.
- Moving authentication to scraper-backed ad validation will strengthen the trust model, but it also introduces operational dependence on page structure, scrape reliability, and the public visibility state of the ad.

## Implementation Snapshot

- The first UI cleanup slice is now implemented in the main mobile path: invitation intake, landlord access setup, escort OTP verification, and gallery entry messaging no longer lead with transport- or diagnostic-oriented framing.
- The flat gallery surface now includes clearer loading, empty, and role-status states so the app no longer looks stalled or half-debug on first load.
- The main entry path now includes a persistent first-run device-role selector for `landlord` versus `escort`, plus an explicit reset path for recovering the device into the other tester mode.
- OTP verification now enforces that the verified backend actor type matches the selected device mode before the secure session is stored locally.
- The shared in-app invitation flow now works for both verified landlords and verified escorts and carries role-specific invitation intent through the signed backend payload.
- Phone-number validation was tightened on both mobile and backend so malformed numbers are rejected before the team mistakes a bad payload for an SMS-provider problem.
- Live field-testing preparation now assumes seeded or manual verification listings from earlier steps are cleaned from shared environments, because those records read like product content to non-technical testers.
- The Android local signer module is now implemented in Kotlin and registered through the Expo local module boundary, matching the shared TypeScript signer contract used on iOS.
- Manual Android device validation now covers landlord bootstrap, escort OTP onboarding, invitation flow, gallery access, protected media actions, and moderation flows against the Laravel hardware-signature contract.
- Android screenshot blocking is now implemented through `expo-screen-capture` for non-development builds, while development builds intentionally bypass the block so UI work and debugging remain practical.
- The current Android runtime path is stabilized around `newArchEnabled=false` and a `FlatList` gallery fallback so the app works reliably in the present Expo SDK 54 / React Native 0.81 environment.
- Report visibility is now aligned with product policy in the gallery surface: landlords see aggregate landlord-report state on owned flats, escorts retain only personal vote and landlord-report state, and reporter identity stays hidden from the reported side.
- Landlord-side reported-escort summaries are now additionally gated behind at least one published flat so a freshly authenticated landlord cannot browse moderation history before contributing a real listing.
- Landlord-side reported-escort summaries are now global once that gate is passed: a participating landlord can see escort reports created by other landlords, including first-report incidents, while reporter identity still stays hidden in the mobile surface.
- Landlord escort moderation now accepts either a raw public ad id or a pasted `amaterky.sk/<id>` URL, and the backend persists that external escort identifier even when no internal escort record exists yet.
- The production Laravel backend is now deployed and reachable at `https://bkp-server.zafo-forum.sk`, including live API validation through `/up` and `/api/verify` against the shared-hosting environment.
- A real two-device landlord rehearsal now succeeded in production: one Android landlord and one iOS landlord completed OTP onboarding, persisted as separate verified landlord rows, and kept distinct hardware public keys and Sanctum tokens.
- Live landlord-to-landlord invitation flow is now proven against the production queue and Vonage path after correcting production credentials and refreshing Laravel config cache.
- The next live expansion path is now explicit: onboard one trusted Android escort via hosted APK and one trusted iOS escort via TestFlight, each backed by a real active `amaterky.sk` ad with a publicly visible phone number.

## Current Validation State

- Validation items 1 through 3 are now satisfied for local device testing:
  - the main tester path is materially de-noised,
  - the tester-facing role lock and role-matched OTP path are implemented,
  - the shared invitation flow works from the verified mobile path,
  - the softer landlord moderation contract now works against external escort ad ids,
  - Android signing is accepted by Laravel on real flows,
  - Android screenshot blocking is confirmed on sensitive surfaces outside development builds.
- One production-like rehearsal is now also satisfied for the landlord slice:
  - the deployed backend served live HTTPS traffic,
  - Android and iOS each onboarded as independent production landlords,
  - live landlord invitation queued and dispatched through the production worker and Vonage configuration once credentials and cached config were corrected.
- Validation items 4 and 5 remain open:
  - a dedicated sideloadable preview APK has not yet been produced as the recorded rollout artifact,
  - the closed-pilot rehearsal and operational sign-off are still future rollout work.
- One additional mandatory expansion is now explicitly parked after the first live checkpoint: redesign escort identity so historical ad IDs and phone numbers attach to one stable internal escort row instead of behaving like a single mutable public identifier.
- One additional authentication expansion is also now explicit: move invitation and activation from inviter-entered phone numbers to scraper-backed ad validation, with the current active ad phone treated as the activation credential.
- Queue and provider operations are now an explicit live-rollout dependency: production invitation delivery requires a running queue worker plus valid Vonage credentials and sender configuration, and Laravel config cache must be refreshed when those settings change.

## Validation

Step 4.3 should be treated as validated only when all of the following are true:

1. The cleaned mobile UI no longer exposes debug-oriented copy, raw transport framing, or internal diagnostic surfaces in the main tester path.
2. The tester-facing device mode remains stable across launches and the secure session cannot silently cross from one role path into the other.
3. Shared verified invitation flow works with the intended role-aware payloads and rejects malformed phone numbers before SMS dispatch.
4. Landlord moderation works against external escort ad identifiers without requiring prior app registration, while the mobile product surface still hides reporter identity appropriately.
5. Android device onboarding completes with KeyMint-backed signing that Laravel accepts.
6. Android screenshot blocking is confirmed on sensitive screens.
7. The preview APK can be distributed and installed reliably by pilot testers.
8. A closed-pilot rehearsal succeeds end to end without security or operational regressions.
9. Production or pilot invitation delivery is exercised with a real queue worker and real SMS-provider configuration rather than only local bootstrap shortcuts.

## Future Development Carry-Forwards

- Keep rollout gating explicit for future sensitive launches instead of assuming local MVP completion is enough.
- Preserve the closed-pilot model until operational behavior is proven stable.
- Treat any broader release as a later decision that requires its own readiness review.
- Keep the OTP provider integration behind a replaceable backend boundary so an operational move from Vonage to Twilio Verify stays a contained rollout task rather than a full auth rewrite.
- Keep the distinction between tester-facing device mode and secure authorization state explicit in future UX work so convenience UI does not leak into the trust model.
- Preserve external public escort identifiers as a first-class moderation input even if future product work introduces richer internal escort profiles.
- Keep landlord-side moderation visibility behind meaningful participation thresholds. The current `publish at least one flat first` rule is the first protection layer, and a later hardening pass should require a landlord to reach stronger trust signals such as one flat confirmed by at least three registered escorts before broader escort-report visibility expands beyond the landlord's own directly attributable incidents.
- Treat the next escort identity redesign as mandatory, not optional polish: the long-term model should hide the real escort row id from product UI while preserving complete historical ad-id and phone-number aliases for moderation and audit use.
- Treat scraper-backed ad validation as part of the authentication boundary, not as optional convenience automation.
