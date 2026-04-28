# ADR 003: iOS Trust Anchor

- **Status:** Accepted
- **Date:** 2026-04-28
- **Scope:** Step 2.2 local development baseline

## Context

Step 2.2 establishes the first native landlord-side trust anchor for iOS workflows. The goal is to let a landlord share selected text or page content from iOS system surfaces such as Safari and Notes into BKP, normalize the incoming payload, and prepare the invitation request that will later be hardware-signed in Step 3.

The roadmap defined five practical requirements for this step:

1. Scaffold the landlord mobile app in `bkp-client/apps/mobile-app/`.
2. Register an iOS share target that can accept plain text and web content.
3. Surface incoming shared content inside the React Native app.
4. Reuse the BKP phone-number sanitization rules already proven in the Chrome trust anchor.
5. Stop at local request drafting because `POST /api/invitations` still requires `auth:sanctum` plus `hardware.signature`.

During implementation, several additional constraints became explicit:

1. The mobile app lives on a Windows-backed drive, but Expo, Metro, prebuild, and EAS commands must be run from a Windows terminal rather than WSL.
2. Expo SDK 54 requires the `expo-share-intent` 5.x line for this integration path.
3. EAS iOS signing becomes ambiguous if the share-extension target name matches the main app target name.
4. Apple Notes exposes two different outbound flows: collaboration-link sharing and content-copy sharing; only the content-copy path is the correct validation path for this step.

## Decision

The local Step 2.2 implementation is accepted with the following architectural decisions.

### App And Extension Packaging

- Use a single Expo TypeScript app in `bkp-client/apps/mobile-app/` as the owning surface for the landlord iOS client.
- Register the share target through `expo-share-intent` rather than building a standalone native extension package.
- Keep the share extension embedded in the mobile app because it is distributed as part of the iOS client, not as a separate product surface.

### Environment And Build Strategy

- Keep the workspace on the Windows-backed drive.
- Run `npm`, `expo`, `prebuild`, and `eas` from Windows so Metro networking, native tooling, and EAS-generated artifacts stay in one runtime environment.
- Use EAS for iOS build and signing validation because the local Windows environment does not yield an inspectable `ios/` project for this integration path.

### Share Intake Contract

- Accept plain-text payloads plus Safari-style page and web URL payloads.
- Use the first receiver slice in the React Native app to surface incoming shared content directly to the landlord.
- Support deep-link fallback handling in addition to the native share-intent bridge so the app remains debuggable while the native surface is being wired.

### Invitation Drafting Model

- Reuse the shared phone-number sanitization rules so Chrome and iOS normalize landlord input consistently.
- Prepare a local invitation draft targeting `POST /api/invitations` with method `POST`.
- Mark the drafted request as blocked on native hardware signing rather than attempting a weaker browser-style fallback.

### iOS Signing And Target Separation

- Give the share extension its own target identity rather than reusing the main app target name.
- Allow the main app target and the share-extension target to share the same Apple distribution certificate.
- Use separate provisioning profiles for `com.bkp.app` and `com.bkp.app.share-extension`.

## Consequences

### Positive

- The iOS landlord flow can be exercised on real devices before the native hardware-signing step is finished.
- Shared text reaches the same sanitization and invitation-draft path regardless of whether the landlord uses desktop Chrome or iOS share flows.
- EAS build output is sufficient to validate the share target on-device without requiring a local macOS/Xcode workstation.
- The mobile app stays aligned with the stronger long-term trust model because it does not bypass the protected invitation route.

### Tradeoffs

- The Step 2.2 app is intentionally incomplete from a business-flow perspective because it cannot submit the invitation yet.
- Expo and EAS configuration for multi-target iOS builds is sensitive to plugin-generated target names and provisioning-profile mapping.
- The operational testing path on iOS is partly UI-specific: Notes collaboration flows can look like share failures even when the extension is working correctly.

## Validation

Step 2.2 was validated with four layers of checks:

1. Windows-side Expo validation via `npx expo config --json` after the share-extension configuration was stabilized.
2. Local TypeScript validation for the mobile app slice, including the share-intent integration and invitation-draft logic.
3. EAS remote iOS build validation confirming separate target provisioning for `BKP` and `BKP Share Extension`.
4. On-device iPhone 11 verification that sharing note content through `Send Copy` (`Poslať kópiu`) opens BKP, delivers `0900111222` as a native share intent, and produces the sanitized draft `+421900111222` for `POST /api/invitations`.

## Operational Notes

- Step 2.2 is complete for local development, not for production rollout.
- In Apple Notes, the successful path is `Send Copy` (`Poslať kópiu`), not the default collaboration mode (`Spolupracovať`).
- A development build can connect to Metro for iterative work, but standalone device validation should use an internal EAS build when Metro dependency is undesirable.

## Future Development Carry-Forwards

- Implement the native hardware-signing layer in Step 3 so the drafted invitation can become a real authenticated `POST /api/invitations` request.
- Decide whether `expo-share-intent` remains sufficient long-term or whether BKP should move to a custom config plugin or more explicit native share-extension ownership.
- Add tighter end-to-end validation for more real-world shared payload shapes such as mixed text, URLs, and formatted Notes content.
- Preserve the distinct target naming and provisioning-profile split in future iOS build changes so EAS signing does not regress.
