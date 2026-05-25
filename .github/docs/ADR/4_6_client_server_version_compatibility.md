# ADR 010: Client and Server Version Compatibility Gate

- **Status:** Accepted
- **Date:** 2026-05-22
- **Scope:** Version numbering policy, runtime compatibility enforcement, mobile update UX, and rollout sequencing for client/server version awareness

## Context

The product has now crossed the line where ad hoc build sharing is no longer enough. Real landlords and escorts are already using production-backed flows, Android preview builds are distributable, and the Expo app configuration is already set to `version: 1.0.0` with `runtimeVersion.policy = appVersion`.

That creates a new operational risk: the mobile client and Laravel backend can drift without a clear compatibility contract.

The project needs to answer five concrete questions:

1. Is `1.0.0` a reasonable first real version number?
2. Which version fields are for human release semantics versus mandatory-update enforcement?
3. Which side is the source of truth when a client becomes obsolete?
4. What should the server return when an installed app is too old?
5. In what order should runtime enforcement and release automation be introduced?

## Decision

### Baseline Release Number Rule

- The current mobile build line may use `1.0.0` as the first real pilot baseline.
- This does **not** mean broad public-store maturity.
- In this project, `1.0.0` means:
  - the signed request model is working,
  - landlord and escort production flows are validated,
  - protected media and OTP flows are working,
  - the app is suitable for controlled real-user rollout.

### Version Numbering Rule

- The project uses `major.minor.patch` versioning for the mobile app.
- The version string communicates release intent, but it is **not** the only compatibility mechanism.
- Practical interpretation:
  - `major`: intentionally breaking or migration-heavy release line
  - `minor`: meaningful functional rollout within the same release line
  - `patch`: backward-compatible fixes, polish, and cosmetic updates
- The digits alone must **not** decide whether an update is mandatory.

### Compatibility Source-of-Truth Rule

- The backend is the source of truth for client support policy.
- The backend must know, per platform, at least:
  - `min_supported_version`
  - `latest_version`
  - `download_url`
  - `force_update`
- A release may force an update even if the semantic version change is small.
- A release may remain compatible even if the semantic version changes across `minor`.

### Request Contract Rule

- The mobile client must send version metadata on API requests.
- The initial required headers are:
  - `X-App-Version`
  - `X-App-Platform`
- A later expansion may add `X-App-Build` or release-channel headers, but those are not required for the first implementation.

### Obsolete Client Response Rule

- When a client is older than the platform's `min_supported_version`, the backend returns `426 Upgrade Required`.
- The backend response must be structured JSON, not only a long human sentence.
- Required response shape:

```json
{
  "code": "client_outdated",
  "force_update": true,
  "min_supported_version": "1.0.0",
  "latest_version": "1.1.0",
  "download_url": "https://example.invalid/download",
  "message": "Prepac, tvoja verzia aplikacie je uz zastarala. Nainstaluj najnovsiu verziu aplikacie."
}
```

- The mobile app is responsible for rendering the final blocking UX in Slovak.

### Update UX Rule

- The mobile app must show a blocking update screen when `force_update=true` is returned.
- The blocking screen should:
  - explain that the installed app is outdated,
  - offer a direct install/download action,
  - avoid mixing update enforcement with OTP recovery logic.
- Reinstallation and OTP onboarding remain part of the normal invitation/authentication flow, not part of the version-check payload.

### Rollout Sequencing Rule

- Runtime compatibility comes before automation.
- The required order is:
  1. ADR and policy freeze
  2. backend version middleware and config
  3. mobile header sending and blocking update screen
  4. push/CI/release automation around the settled contract
- `git push` should validate version discipline later, but should not be the first place where version policy is invented.

## Consequences

### Positive

- Client/server drift gets a clear runtime control point.
- Forced updates become a product decision instead of an improvised support conversation.
- The app can keep simple semantic versions without overloading them with every compatibility edge case.
- Future release automation has a stable contract to validate against.

### Tradeoffs

- The backend now owns one more global rollout policy surface.
- Every API request will carry extra metadata that must remain consistent across mobile builds.
- The product must define and maintain a real download URL per platform before forced updates are turned on for live testers.

## Minimal Implementation Plan For This Repo

### Step 1: Backend Configuration

- Add a dedicated backend config surface, preferably a new file such as:
  - `backend/config/client_versions.php`
- Define per-platform policy there, for example:
  - `android.min_supported_version`
  - `android.latest_version`
  - `android.download_url`
  - `android.force_update`
  - `ios.min_supported_version`
  - `ios.latest_version`
  - `ios.download_url`
  - `ios.force_update`
- Back these values with `.env` entries so production can update the compatibility gate without a code rewrite.

### Step 2: Backend Middleware

- Add a middleware in `backend/app/Http/Middleware/` that:
  - reads `X-App-Version`
  - reads `X-App-Platform`
  - compares the client version against configured minimums
  - returns `426 Upgrade Required` JSON when the client is obsolete
- Apply it to the API group after the request has enough context to identify mobile API traffic but before normal controller work proceeds.
- Keep the first implementation simple: unsupported or missing headers should be tolerated for browser tooling and non-mobile callers until the mobile contract is fully rolled out.

### Step 3: Mobile Header Sending

- Read the app version from Expo constants or app config in the mobile app.
- Add version headers to the shared request layer used by invitation, verification, gallery, and other protected API calls.
- The initial target is the existing API request helpers under `apps/mobile-app/src/` rather than scattered per-screen code.

### Step 4: Mobile Blocking Screen

- Add one reusable outdated-client UI state in the mobile app.
- When the backend returns `code=client_outdated`, the app should:
  - stop normal flow,
  - show the Slovak blocking message,
  - open the provided `download_url` on user action.
- The first version can be global and simple. It does not need release-notes UX.

### Step 5: Release Discipline Later

- After runtime enforcement exists, add lightweight release checks:
  - confirm `app.json` version bump on release builds,
  - confirm backend compatibility config is updated,
  - optionally validate changelog or release notes.
- Leave git-tag or `release:push` automation for a later phase.

## Repository-Specific Notes

- The mobile app already exposes `expo.version = 1.0.0` in `apps/mobile-app/app.json`.
- The mobile app already uses `runtimeVersion.policy = appVersion`, which is compatible with this decision and should stay aligned with release numbering.
- The backend currently has no app-version compatibility contract, so the first implementation should keep the server policy explicit and isolated rather than hiding it inside unrelated config.

## Future Development Carry-Forwards

- Keep semantic versioning human-readable and backend compatibility policy explicit.
- Do not couple forced-update behavior to OTP or reinstall recovery wording.
- If browser tooling or Chrome extension traffic later needs version awareness, give it a separate compatibility policy rather than assuming the mobile contract fits every client.
