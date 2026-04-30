# `project_sheet.md`

## Step 1: The Core Infrastructure (Laravel Server)

- **Status:** Completed for local development on April 24, 2026.
- **Detailed Documentation:** See [ADR/1_core_infrastructure_laravelserver.md](ADR/1_core_infrastructure_laravelserver.md).

- **Action:** Establish the foundational Laravel backend API to manage users, handle secure SMS invitations via Vonage, provide real-time messaging via Reverb, and enforce hardware-backed cryptographic security.
- **Task:**
  1. **Initialize Project:** Create a fresh Laravel 11/12 application and configure PostgreSQL as the primary database.
  2. **Database Migrations & Models:**
     - Create `Landlord` model (fields: `id`, `public_key`, `is_verified`, `created_at`).
     - Create `Escort` model (fields: `id`, `phone_number`, `public_key`, `created_at`).
     - Create `Invitation` model (fields: `id`, `landlord_id` (FK), `phone_number`, `otp_token`, `status` [enum: pending, accepted, declined], `expires_at`).
  3. **SMS Gateway Integration:**
     - Install the `laravel/vonage-notification-channel` package via Composer.
     - Configure Vonage credentials (`VONAGE_KEY`, `VONAGE_SECRET`) in the `.env` file.
     - Create a Laravel Notification `InvitationSmsNotification` that sends the generic payload ("You have been invited...") using Vonage's SMS client.
  4. **WebSockets (Laravel Reverb):**
     - Install Laravel Reverb using `php artisan install:broadcasting --reverb`.
     - Configure private broadcasting channels (`chat.{id}`) in `routes/channels.php` to ensure real-time messages are completely gated.
  5. **API Endpoints (REST):**
     - `POST /api/invitations`: Accepts a phone number from the Landlord's Share Extension, creates an `Invitation` record, generates an OTP, and dispatches the `InvitationSmsNotification` to a queue worker.
     - `POST /api/verify`: Accepts the OTP from the Escort app along with the generated hardware `public_key`, verifies the OTP, and permanently stores the `public_key` in the `Escort` record.
  6. **Hardware Security Middleware:**
     - Create `VerifyHardwareSignature` Middleware.
     - Logic: Intercept incoming API and WebSocket requests, extract the device signature from the `X-Hardware-Signature` header, fetch the user's stored `public_key` from the database, and verify the EC (Elliptic Curve) signature using PHP's OpenSSL functions to authenticate the request.
- **Accessibility (ARIA):**
  - _Not Applicable._ This phase is strictly backend API and WebSocket configuration; no frontend HTML or UI is generated.
- **Test Plan:**
  - `test_invitation_creation_sends_generic_sms`: Mocks the Vonage API, triggers the invitation endpoint, and asserts a database entry is created and the strictly generic SMS content is queued.
  - `test_otp_verification_stores_public_key`: Provides a valid OTP pair and asserts that the `public_key` is correctly tied to the database record.
  - `test_middleware_accepts_valid_hardware_signature`: Mocks a valid EC signature generation, passes it through the middleware, and asserts a successful request lifecycle (HTTP 200).
  - `test_middleware_rejects_invalid_hardware_signature`: Passes a tampered payload or incorrect signature and asserts an HTTP 401/403 response.
  - `test_reverb_websocket_connection_requires_auth`: Attempts to connect to the Reverb private channel without a valid hardware signature and asserts the connection is immediately dropped.

### Implementation Outcome

- Laravel 12 backend scaffolded in `backend/` and configured to run locally on PostgreSQL.
- Core tables, models, enums, and API controllers are implemented for `Landlord`, `Escort`, and `Invitation`.
- Generic invitation SMS delivery is wired through the Vonage notification channel.
- Reverb broadcasting is installed and private channel authorization is gated by authenticated and hardware-signed requests.
- Hardware signature verification now includes canonical request signing, timestamp freshness checks, and nonce-based replay protection.
- `POST /api/protected/ping` exists as the initial signed-request probe route for validating the protected middleware stack.
- Local validation completed through focused feature tests, successful PostgreSQL migrations, and a live end-to-end API flow against the PostgreSQL-backed Laravel server.

### Remaining Operational Notes

- Real SMS delivery still requires valid `VONAGE_KEY`, `VONAGE_SECRET`, and sender configuration.
- Continuous websocket and queued notification processing still require the relevant local processes to be running during manual testing.

### Future Development Notes

- Treat SMS OTP as onboarding proof only; future sensitive actions should rely on stronger trust checks than phone-number control alone.
- Define production retention, redaction, and log-handling rules for invitations, phone numbers, public keys, queue payloads, and backups before live rollout.
- Keep the landlord bootstrap token flow restricted to `local` and `testing`; production should replace it with a dedicated enrollment path.

## Step 2.1: The Trust Anchor (Chrome Extension for Landlords)

- **Status:** Completed for local development on April 25, 2026.
- **Detailed Documentation:** See [ADR/2_1_chrome_trust_anchor.md](ADR/2_1_chrome_trust_anchor.md).

- **Action:** Build a standalone Chrome Manifest V3 extension that allows landlords on desktop to highlight phone numbers on `amaterky.sk` and securely trigger the backend invitation process.
- **Planned Client Workspace Layout:**
  - `bkp-client/tools/chrome-trust-anchor/` for the standalone Chrome extension.
  - `bkp-client/apps/mobile-app/extensions/ios-share-extension/` reserved for the separate iOS Trust Anchor that will stay embedded in the mobile app.
  - `bkp-client/apps/mobile-app/` remains the owning home for the React Native landlord app once it is scaffolded.
- **Task:**
  1. **Initialize Project:** Create a standard Chrome Extension directory structure (`manifest.json`, `background.js`, `popup.html`, `popup.js`).
  2. **Manifest Configuration:** Set manifest version to 3. Request permissions for `contextMenus`, `storage` (to save the Landlord's Sanctum token), and `activeTab`. Declare host permissions strictly for `*://*.amaterky.sk/*` and your Laravel backend URL.
  3. **Authentication (Popup UI):** \* Create a simple `popup.html` interface where the landlord can paste their Laravel Sanctum Bearer Token (generated via the local bootstrap endpoint for now).
     - Use `chrome.storage.local` to securely persist this token.
     - Provide a local `Send test invite` button so the browser-safe endpoint can be verified from the popup before testing the page-selection context menu on `amaterky.sk`.
  4. **Context Menu Injection:** \* In `background.js`, use `chrome.contextMenus.create` to add a "Pozvat do BKP" option.
     - Configure it so it only appears when text is highlighted (`contexts: ["selection"]`) and only on `amaterky.sk` URLs (`documentUrlPatterns`).
  5. **API Communication:**
     - Listen for the `chrome.contextMenus.onClicked` event.
     - Extract the highlighted text (`info.selectionText`) and sanitize it (strip spaces, ensure `+421` or standard format).
     - Retrieve the Sanctum token from storage.
     - Execute a `fetch()` POST request to a dedicated browser-safe invitation endpoint with the token in the `Authorization: Bearer` header and the phone number in the payload.
- **Implementation Order:**
  1.  Create the client workspace directories so Chrome and iOS stay physically separated from the start.
  2.  Scaffold the Chrome extension in `tools/chrome-trust-anchor/` with MV3 manifest, popup, background worker, and a small shared sanitization utility.
  3.  Validate the popup token storage flow before wiring invitation calls.
  4.  Add a dedicated browser-safe invitation endpoint because the current `/api/invitations` route is protected by `hardware.signature` and is not directly callable from the Chrome extension.
  5.  Wire the popup test action and the context-menu-driven invitation flow against that browser-safe endpoint using the stored bearer token.
  6.  Reserve the iOS extension directory only; do not couple its native extension code to the Chrome extension implementation.
- **Accessibility (ARIA):**
  - In `popup.html`, ensure the popup fields have visible labels and the status area announces invite results via `aria-live`.
- **Test Plan:**
  - `test_phone_number_sanitization`: Unit test the regex/function that cleans the highlighted text.
  - `manual_e2e_storage`: Verify pasting a token into the popup correctly saves to `chrome.storage.local`.
  - `manual_e2e_invitation_flow`: Load unpacked extension, highlight a mock number on the target URL, click the context menu, and verify via Laravel logs/DB that the `/api/browser/invitations` endpoint received the authenticated request.

### Step 2.1 Implementation Outcome

- Chrome Manifest V3 trust-anchor workspace is implemented in `bkp-client/tools/chrome-trust-anchor/` with manifest, popup UI, background worker, and shared phone sanitization utility.
- The popup now persists the backend URL and Sanctum bearer token in `chrome.storage.local` and supports a local `Send test invite` flow.
- The context menu is restricted to selected text on `amaterky.sk` pages and dispatches authenticated invitation requests through the dedicated browser-safe endpoint.
- Invite feedback is surfaced both through the extension badge and the popup, including a transient success state after manual invite submission.
- Local validation completed through focused Node tests for phone sanitization plus manual backend invite verification against the Laravel browser-safe route.

### Step 2.1 Remaining Operational Notes

- Completion here means local development readiness, not live-market validation with real landlord and escort data.
- A production-like smoke test with real target-site content is still advisable before treating the desktop trust anchor as field-ready.

### Step 2.1 Notes

- The Chrome Trust Anchor is intentionally standalone because its runtime, packaging, permissions, and release flow are unrelated to the iOS share extension.
- The iOS Trust Anchor should remain inside the future React Native app because it is distributed as part of the iOS client rather than as an independent desktop/browser deliverable.
- For Step 2.1, bearer-token authentication is the practical bridge to the backend, but it needs a dedicated browser-safe invitation route because the current `/api/invitations` endpoint also requires hardware-backed signing.
- Local validation now covers both popup-based invite submission and the real queue-backed SMS delivery path with Vonage configured in the local environment.

## Step 2.2: The Trust Anchor (iOS Share Extension via React Native)

- **Status:** Completed for local development on April 28, 2026.
- **Detailed Documentation:** See [ADR/2_2_ios_trust_anchor.md](ADR/2_2_ios_trust_anchor.md).
- **Action:** Initialize the React Native Expo mobile app and prepare the iOS Share Extension path that will capture phone numbers highlighted in Safari.
- **Task:**
  1. **Initialize Project:** Scaffold the Expo TypeScript app directly under `bkp-client/apps/mobile-app/` on the Windows-backed drive so Metro and device networking stay compatible.
  2. **Share Extension Plugin:**
     - Install a community plugin such as `expo-share-intent` or replace it with a custom Expo Config Plugin if the package proves too limiting during prebuild.
  3. **App Configuration (`app.json` / Expo config):**
     - Define the iOS bundle identifier.
     - Configure the Share Extension target to accept plain-text payloads (`public.plain-text`).
     - Use a share-extension target name distinct from the main app target name so EAS credential mapping does not confuse the app profile with the extension profile.
  4. **React Native Receiver Logic:**
     - Add the first receiver slice that can accept shared text, surface it inside the app, and reuse the same phone-number sanitization rules as the Chrome Trust Anchor.
  5. **Invitation Payload Drafting:**
     - Because `POST /api/invitations` is still protected by `auth:sanctum` plus `hardware.signature`, Step 2.2 should only prepare the sanitized request payload and local invitation draft.
     - The actual signed request send belongs to Step 3, when the native hardware-signing layer is implemented.
- **Development Environment Note:**
  - Keep the mobile app on the Windows-backed drive, but use a Windows terminal for `npm install`, `expo start`, and `expo prebuild` so Metro, Expo tooling, and future Android/iOS integration stay in one runtime environment.
  - Windows prebuild currently materializes the Android native project and share-intent filters, but it does not generate a local `ios/` project in this environment. The actual iOS share-extension target still needs a macOS/Xcode or EAS-based path for native inspection and device validation.
- **Accessibility (ARIA):**
  - Use React Native accessibility props such as `accessible={true}` and a clear `accessibilityLabel` on the share-confirmation surface.
- **Test Plan:**
  - `test_share_intent_configuration`: Verify Expo config generates the Android share filters on Windows `npx expo prebuild`, then verify the iOS share target from a macOS/Xcode-capable environment.
  - `test_phone_number_sanitization`: Unit test the shared text-cleaning logic.
  - `manual_e2e_safari_share`: Install a development build later, highlight text in Safari, open the BKP share target, and verify the app receives and sanitizes the shared payload.

### Step 2.2 Implementation Outcome

- The Expo TypeScript mobile app is scaffolded in `bkp-client/apps/mobile-app/` and uses `expo-share-intent` to register the BKP iOS share target.
- The app now receives native share payloads, normalizes phone numbers with the shared sanitizer, and prepares a local invitation draft pointing at `/api/invitations`.
- EAS iOS device builds now provision both the main app target and the share-extension target correctly after separating their target names.
- On-device validation succeeded on iPhone 11: sharing note content into BKP delivered `0900111222` into the app as a native share intent and produced the sanitized draft `+421900111222` for `POST /api/invitations`.

### Step 2.2 Remaining Operational Notes

- In Apple Notes, the successful content-share path is `Send Copy` (`Poslať kópiu`), not the default collaboration-link path (`Spolupracovať`).
- Step 2.2 stops at preparing the signed-route draft; the actual native hardware signature and request submission still belong to Step 3.

## Step 3: The Secure Client Apps (React Native via Expo)

- **Status:** Partially completed for the validated iOS landlord slice on April 29, 2026.
- **Action:** Implement the shared mobile security foundation once, then complete the iOS and Android platform layers separately where hardware signing, screen-capture behavior, and native key storage differ.
- **Detailed Documentation:** See [ADR/3_secure_client_signer_contract.md](ADR/3_secure_client_signer_contract.md).
- **Shared Cross-Platform Tasks:**
  1. **Cryptographic Contract Definition:**
  - Freeze the exact signing contract before writing native code: curve `secp256r1`, exported public-key format, signature encoding, and canonical payload format.
  - Match the backend middleware contract exactly: `Timestamp + Nonce + Method + Path + BodyHash`, joined by newlines, where `Path` includes the query string and `BodyHash` is `sha256(request body)`.
  2. **Shared TypeScript Security Layer:**
  - Define a common `SecureSigner` interface in TypeScript for `generateKeyPair`, `getPublicKey`, and `signCanonicalPayload`.
  - Build a shared canonical-payload builder and body-hash utility so iOS and Android feed the native signer identically.
  3. **Device Registration & Authentication Flow:**
  - Build the Escort onboarding UI: Enter phone number -> Receive SMS OTP -> Enter OTP.
  - On OTP submit: generate the hardware-bound EC key pair through the platform signer, send the OTP plus `public_key` to `/api/verify`, and store the returned Laravel Sanctum bearer token in `expo-secure-store`.
  - Keep the private key inside the native hardware-backed store; only the session token lives in `expo-secure-store`.
  4. **Secure API Interceptor (Axios/Fetch):**
  - Build an HTTP interceptor that secures every protected `/api/*` request.
  - Inject `Authorization: Bearer <Sanctum-Token>`.
  - Generate a unique UUID for `X-Hardware-Nonce` and the current Unix timestamp for `X-Hardware-Timestamp`.
  - Construct the canonical payload, sign it through the platform signer, and attach `X-Hardware-Signature`.
  5. **Zero App-Managed Data At Rest:**
  - Configure the HTTP client to request non-cached responses (`Cache-Control: no-store`).
  - Install `expo-image` and use memory-only caching for sensitive images wherever the library permits.
  - Ensure no sensitive message, profile, or media payloads are written by the app to `AsyncStorage`, SQLite, or another explicit persistent store.
  6. **WebSockets (Laravel Reverb):**
  - Install `laravel-echo` and `pusher-js`.
  - Configure the Echo client to connect to the Reverb server.
  - Override the Echo `authorizer` so the `/broadcasting/auth` handshake goes through the Secure API Interceptor and is hardware-signed before the socket is established.
- **iOS-Specific Tasks:**
  1. **Secure Enclave Signer Module:**
  - Implement an Expo local module in Swift that creates and uses a Secure Enclave-backed `secp256r1` key when the device permits silent signing after unlock.
  - Export the public key in the exact format accepted by Laravel and return base64 signatures in the agreed encoding.
  2. **Share-Intent Completion Path:**
  - Keep the current Step 2.2 handoff model: the share target delivers the sanitized payload into the BKP host app, and the host app performs the signed `POST /api/invitations` request.
  - Update the invitation drafting flow to use the shared Secure API Interceptor and successfully dispatch the hardware-signed invite request.
  3. **Screen-Capture Mitigation:**
  - Install `expo-screen-capture` and enable iOS capture-detection handling.
  - Treat iOS protection as best-effort visual shielding or redaction, not as the same hard OS block that Android provides with `FLAG_SECURE`.
- **Android-Specific Tasks:**
  1. **KeyMint / Android Keystore Signer Module:**
  - Implement the Expo local module in Kotlin using Android Keystore / KeyMint-backed `secp256r1` keys with silent signing after device unlock.
  - Match the same TypeScript signer interface and public-key export format used on iOS.
  2. **Screen-Capture Blocking:**
  - Install `expo-screen-capture` and enforce `FLAG_SECURE` on sensitive screens to block screenshots and screen recording at the OS level.
  3. **Parity Validation:**
  - Validate that Android-generated public keys and signatures verify against the same Laravel middleware and canonical-payload builder used by iOS.
- **Accessibility (ARIA):**
  - Use `accessibilityLabel`, `accessibilityHint`, and `accessible={true}` for all onboarding inputs (Phone Number, OTP) and buttons.
  - Ensure error states (invalid OTP, network error) are announced to screen readers using `accessibilityLiveRegion`.
  - Keep the iOS share-result confirmation screen and the Android onboarding screens aligned on the same spoken labels and error semantics.
- **Test Plan:**
  - `test_canonical_payload_builder`: Unit test to ensure the generated string matches the Laravel middleware contract exactly.
  - `test_interceptor_injects_headers`: Unit test mocking the HTTP client to ensure the Nonce, Timestamp, Sanctum Token, and Signature headers are attached.
  - `manual_e2e_escort_onboarding_ios`: Run on iOS device, complete the OTP flow, verify the public key is saved in PostgreSQL, and confirm the bearer token is stored while the private key remains hardware-bound.
  - `manual_e2e_escort_onboarding_android`: Run on Android device and verify the same onboarding and key-registration contract.
  - `manual_e2e_reverb_auth_ios`: Connect to a private Echo channel from iOS and verify the backend accepts the hardware-signed auth request.
  - `manual_e2e_reverb_auth_android`: Connect to a private Echo channel from Android and verify the same signed auth path.
  - `manual_e2e_screenshot_block_ios`: Attempt capture on iOS and verify the app applies its best-effort privacy shield or redaction behavior.
  - `manual_e2e_screenshot_block_android`: Attempt capture on Android and verify `FLAG_SECURE` blocks or blanks the capture.
  - `manual_e2e_signed_share_invite_ios`: Share a sanitized number from Safari into BKP on iOS and verify the host app submits a hardware-signed `POST /api/invitations` request.

---

### A Quick Architecture Check Before You Code

The critical architectural boundary is still Task 1, but it should now be treated as one shared TypeScript contract backed by two native implementations: a Swift Secure Enclave signer for iOS and a Kotlin KeyMint / Android Keystore signer for Android. Standard Expo still does not provide an off-the-shelf silent hardware EC signer for this use case, so a small **Expo Local Module** remains the expected implementation path.

### Step 3 Implementation Outcome

- The shared TypeScript signer contract, canonical payload builder, hardware-signature header assembly, and secure device-token storage are implemented.
- The iOS local Expo module now provisions and uses a Secure Enclave-backed `secp256r1` signing key that matches the Laravel verification contract.
- Landlord device onboarding is implemented through `POST /api/landlords/tokens`, and the returned Sanctum bearer token is stored in `expo-secure-store` while the private key remains inside Secure Enclave.
- On-device validation succeeded on iPhone 11 for three invitation sources: manual input, Apple Notes native share, and Safari selected-text share.
- Each validated source successfully produced a hardware-signed `POST /api/invitations` request, Laravel created the invitation row, the queue worker processed the notification, and Vonage delivered a real SMS to a live handset.

### Step 3 Remaining Operational Notes

- Local iPhone testing against a WSL-hosted Laravel server requires Windows LAN exposure of the backend, including a `netsh interface portproxy` rule from the Windows LAN IP to the current WSL IP on port `8000`.
- Local manual testing still requires three active processes: Laravel (`php artisan serve --host=0.0.0.0 --port=8000`), Metro (`npx expo start --dev-client --clear`), and a queue worker (`php artisan queue:work`) when verifying SMS delivery.
- Vonage API SMS delivery is proven working, but the Vonage web dashboard currently returns `403 AccessDenied` after MFA; treat that as a dashboard issue, not as a blocker for the BKP invitation pipeline.

### Step 3 Remaining Scope

- Android KeyMint / Keystore signing, Android screenshot blocking, and Android interoperability validation are still pending.
- Escort OTP onboarding through `/api/verify` remains future Step 3 scope and has not yet been validated on device.
- Reverb authorization through the signed mobile client and screenshot/capture mitigation behavior are still pending manual validation.

### Step 3 Product Gap

- The current Step 3 outcome is a validated security and transport skeleton for the iOS landlord path, not a user-ready MVP surface.
- The app currently behaves as a workflow demonstrator for onboarding, share intake, and signed invitation submission rather than as a production-shaped landlord or escort experience.
- The next implementation focus should move from proving the cryptographic boundary to building real product UI and UX on top of the validated iOS landlord foundation.
- That next slice should include a proper landlord entry flow, a stable invitation/send experience, and the first escort-facing onboarding UX before treating the mobile app as MVP-complete.

## Step 4.1: The Flat Gallery Backend Contract

- **Action:** Implement the backend-first flat gallery contract so product UI can build on stable data, paginated list semantics, protected media delivery, bilateral reports, RBAC, access-scope, and signed-upload behavior.
- **Prerequisite:** This step assumes the validated iOS landlord Step 3 slice is already in place.
- **Task:**
  1. **Backend Data Model (Laravel):**
  - Create `Flat`, `FlatPhoto`, `Vote`, and bilateral `FlatReport` models plus migrations.
  - Ensure the core relationships exist: Landlord `hasMany` Flats, Flat `hasMany` Photos, Flat `hasMany` Votes, and Flat `hasMany` Reports.
  2. **Protected Endpoints (`VerifyHardwareSignature`):**
  - `POST /api/flats`: Owner only, creates a Flat under the authenticated Landlord.
  - `GET /api/flats`: Returns a paginated flat list scoped to the authenticated actor. Freeze the default and maximum `per_page` behavior now so mobile can depend on it safely.
  - `POST /api/flats/{id}/photos`: Owner only, accepts `multipart/form-data` uploads.
  - `GET /api/photos/{id}/content`: Delivers gallery media only through the authenticated and hardware-signed backend route; the API must not require the mobile client to depend on public storage URLs.
  - `DELETE /api/photos/{id}`: Owner only, deletes a photo.
  - `POST /api/flats/{id}/vote`: Guest only, records a hardware-signed vote or mark from an Escort.
  - `POST /api/flats/{id}/report-landlord`: Escort only, creates or edits a hardware-signed landlord report using the fixed reasons `pimp`, `harassing`, or `did_not_keep_agreement`.
  - `POST /api/flats/{id}/report-escort`: Landlord only, creates or edits a hardware-signed escort report using the fixed reasons `drugs`, `hygiene`, or `did_not_pay`.
  3. **Authorization And Scope:**
  - Freeze the owner-only mutation rules for flat creation, photo upload, and photo deletion.
  - Freeze the escort access rule for `GET /api/flats` and `GET /api/photos/{id}/content` so Step 4.2 does not imply a public flat directory or public gallery media.
  - Freeze the bilateral reporting rule: escorts may report only landlords tied to flats they can access, landlords may report only escorts tied back to the landlord through an accepted invitation relationship, and each side may edit its existing report through the same signed endpoint rather than creating duplicate rows.
  4. **Multipart Signing Validation:**
  - Explicitly validate that `multipart/form-data` photo uploads follow the same Secure API Interceptor contract as protected JSON requests.
  - Prove the backend verifies the signed upload request without weakening the Step 3 signature boundary.
- **Accessibility (ARIA):**
  - _Not Applicable._ This slice is backend contract and authorization work; no product UI is introduced here.
- **Test Plan:**
  - `test_flat_creation_requires_landlord_role`: Assert that an Escort's hardware signature cannot access the `POST /api/flats` endpoint.
  - `test_flat_gallery_scope_requires_approved_access`: Assert that an Escort only receives flats allowed by the invitation/access rule.
  - `test_flat_listing_is_paginated_for_landlords`: Assert that `GET /api/flats` returns the frozen paginated list shape and respects `per_page` boundaries.
  - `test_escort_can_fetch_photo_content_via_protected_media_route`: Assert that a permitted Escort receives media only through the authenticated content endpoint.
  - `test_guest_vote_is_hardware_signed`: Assert that a vote is successfully recorded when accompanied by a valid canonical payload signature.
  - `test_escort_can_report_landlord_with_fixed_reason_codes`: Assert that escort-side reports accept only the fixed landlord-report reasons.
  - `test_escort_can_edit_existing_landlord_report_reason`: Assert that escort-side reports update the existing report instead of creating duplicates.
  - `test_landlord_can_report_escort_with_fixed_reason_codes`: Assert that landlord-side reports accept only the fixed escort-report reasons.
  - `test_landlord_can_edit_existing_escort_report_reason`: Assert that landlord-side reports update the existing report instead of creating duplicates.
  - `test_multipart_upload_signature_contract`: Assert that owner photo uploads verify correctly through the same hardware-signature middleware contract used by JSON requests.

  ### Step 4.1 Contract Freeze Notes
  - The paginated `GET /api/flats` response shape is now treated as a frozen mobile contract and is covered by focused resource-shape assertions.
  - Bilateral reports are intentionally `write-edit`, not append-only: each actor updates their existing report for the same flat and counterpart instead of creating parallel duplicates.

### Step 4.1 Implementation Snapshot

- `GET /api/flats` is now paginated and returns mobile-facing resource shapes rather than raw storage internals.
- Gallery photos are now exposed through authenticated `GET /api/photos/{id}/content` delivery instead of public storage URLs.
- Bilateral reporting is now part of the backend contract with fixed reasons on both sides:
  - Escort reports landlord: `pimp`, `harassing`, `did_not_keep_agreement`
  - Landlord reports escort: `drugs`, `hygiene`, `did_not_pay`
- Focused Laravel tests now cover pagination, protected media delivery, signed multipart upload verification, and both report directions.
- The first mobile consumer now exists in the mobile app as a signed flat-list loader that targets paginated `GET /api/flats` and prepares signed requests for each protected `content_url`.

### Step 4.1 Product Intent

- This slice exists to remove backend and integration ambiguity before the real gallery UX is built.
- It should be treated as a short enabling step, not as the user-facing product milestone.
