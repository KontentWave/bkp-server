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
- Escort OTP onboarding is now implemented in-app through `POST /api/verify`, stores escort session metadata in secure storage, and has been manually validated on iPhone with both successful and invalid-OTP outcomes.
- On-device validation succeeded on iPhone 11 for three invitation sources: manual input, Apple Notes native share, and Safari selected-text share.
- Each validated source successfully produced a hardware-signed `POST /api/invitations` request, Laravel created the invitation row, the queue worker processed the notification, and Vonage delivered a real SMS to a live handset.

### Step 3 Remaining Operational Notes

- Local iPhone testing against a WSL-hosted Laravel server requires Windows LAN exposure of the backend, including a `netsh interface portproxy` rule from the Windows LAN IP to the current WSL IP on port `8000`.
- Local manual testing still requires three active processes: Laravel (`php artisan serve --host=0.0.0.0 --port=8000`), Metro (`npx expo start --dev-client --clear`), and a queue worker (`php artisan queue:work`) when verifying SMS delivery.
- Vonage API SMS delivery is proven working, but the Vonage web dashboard currently returns `403 AccessDenied` after MFA; treat that as a dashboard issue, not as a blocker for the BKP invitation pipeline.

### Step 3 Remaining Scope

- Android KeyMint / Keystore signing, Android screenshot blocking, and Android interoperability validation are still pending.
- Reverb authorization through the signed mobile client and screenshot/capture mitigation behavior are still pending manual validation.

### Step 3 Product Gap

- The current Step 3 outcome is a validated security and transport skeleton for the iOS landlord path, not a user-ready MVP surface.
- The app currently behaves as a workflow demonstrator for onboarding, share intake, and signed invitation submission rather than as a production-shaped landlord or escort experience.
- The next implementation focus should move from proving the cryptographic boundary to building real product UI and UX on top of the validated iOS landlord foundation.
- That next slice should include a proper landlord entry flow, a stable invitation/send experience, and the first escort-facing onboarding UX before treating the mobile app as MVP-complete.

## Step 4.1: The Flat Gallery Backend Contract

- **Detailed Documentation:** See [ADR/4_1_flat_gallery_backend_contract.md](ADR/4_1_flat_gallery_backend_contract.md).
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

## Step 4.2: The Landlord's Flat Gallery (Productization)

- **Status:** Closed for MVP on May 2, 2026 for the validated iOS landlord and escort slices. Owner flow, escort OTP onboarding, guest-mode rendering, guest action submission, and the `FlashList` gallery renderer are now working. Advanced media UX and release-style cache/offline proof are intentionally deferred.
- **Detailed Documentation:** See [ADR/4_2_landlord_flat_gallery_productization.md](ADR/4_2_landlord_flat_gallery_productization.md).
- **Action:** Build the first product-facing landlord and escort gallery experience on top of the completed Step 4.1 backend contract, with role-based regimes (Owner vs. Guest), anonymous voting and reporting visibility, and no app-managed persistent storage for gallery media or data.
- **Prerequisite:** This step assumes completed Step 4.1 backend work and the validated iOS landlord Step 3 slice. Escort gallery and voting flows additionally depend on the still-pending Step 3 escort OTP onboarding flow.
- **Task:**
  1. **Frontend Data Layer (`@tanstack/react-query`):**
  - Install `@tanstack/react-query`.
  - Configure the `QueryClient` globally. _Security Note: React Query keeps data in RAM by default. Ensure no experimental disk-persister plugins are installed._
  - Create custom hooks: `useFlatsQuery()`, `useUploadPhotoMutation()`, `useVoteMutation()`, `useReportLandlordMutation()`, and `useReportEscortMutation()`. Inject the Step 3 `Secure API Interceptor` into their fetch or axios instances to guarantee every query and mutation is hardware-signed.
  2. **Owner Dashboard & Uploads (`expo-image-picker` & `manipulator`):**
  - Install `expo-image-picker` and `expo-image-manipulator`.
  - Build the Owner regime UI, including a real flat-creation flow before photo management and contact shortcuts for phone, email, WhatsApp, Telegram, and Viber when the flat resource exposes them.
  - Treat `title`, `description`, `contact.phone`, and `contact.email` as required owner inputs. Messenger shortcut URLs remain optional.
  - **Upload Flow:** Trigger image picker -> On selection, immediately pass the URI to `expo-image-manipulator` to resize (e.g., max 1080px width) and compress (e.g., 0.7 quality) without introducing app-managed persistent gallery storage -> Convert to FormData -> Dispatch `useUploadPhotoMutation`.
  3. **Secure Gallery Rendering (`@shopify/flash-list` & `expo-image`):**
  - Install `@shopify/flash-list` and `expo-image`.
  - Build the `FlatGallery` component using a masonry or multi-column `FlashList` for 60fps scrolling.
  - Render thumbnails using `<Image source={{ uri }} cachePolicy="memory" />` to avoid app-managed persistent gallery caching on iOS and Android.
  4. **Regime Overlays & Interactions:**
  - Derive the active role from secure session metadata stored alongside the device token after landlord token issuance or escort OTP verification. Do not rely on a manual role switch in the product surface.
  - **If Owner:** Render a flow for creating flats, a floating action button (FAB) for adding photos, tiny trash-can icons over each image for deletion, and the landlord-side escort-report action using the fixed Step 4.1 reasons.
  - **If Guest:** Render the gallery vote action and the escort-side landlord-report action using the fixed Step 4.1 reasons.
  - The product UI must treat voting and reporting as anonymous from the counterpart's perspective: landlords may see aggregate vote counts and their own flat state, escorts may see their own vote state, but neither side may see which specific counterpart voted or reported against them.
  - The product UI must not expose reporter identities, voter identities, or per-counterparty moderation history to the reported side.
  - If reporter identity needs to be reviewed later, that visibility belongs only in a future admin or moderator website outside the mobile product surface.
- **Accessibility (ARIA):**
  - Add `accessibilityRole="image"` and meaningful `accessibilityLabel`s to all `expo-image` components (e.g., `accessibilityLabel={"Photo of flat: " + flat.title}`).
  - Ensure Owner controls (Delete/Upload/Report) and Guest controls (Vote/Report) have `accessibilityRole="button"` and `accessibilityHint`s describing the action.
  - Ensure the owner flat-creation flow has labeled inputs and clearly announced validation errors.
  - Use `accessibilityLiveRegion="polite"` on vote and report status updates so assistive technology announces successful submissions without exposing counterpart identity data.
- **Test Plan:**
  - `test_image_manipulator_compresses_payload`: Mock the image picker and assert the manipulator outputs a smaller file size before passing it to the mutation.
  - `manual_e2e_owner_flat_creation_flow`: Log in as a Landlord, create a Flat, and verify it appears in the owner dashboard before photo upload.
  - `manual_e2e_owner_upload_delete_flow`: Log in as a Landlord on a physical device, load the product gallery, create or open an owned flat, upload one photo through the gallery component, verify the image appears in the list, delete the same photo, and verify it disappears without exposing any escort-only controls.
  - `manual_e2e_owner_report_anonymity`: Log in as a Landlord, report an Escort, and verify the landlord receives only submission confirmation while the escort-facing surfaces do not reveal who reported them.
  - `manual_e2e_guest_vote_and_report_anonymity`: Log in as an Escort, vote on and report a flat or landlord, and verify the escort receives only personal submission state while the landlord-facing surfaces expose only aggregate state and never the acting escort identity.
  - `manual_e2e_guest_memory_cache_check`: Log in as an Escort, scroll through the gallery, fully close the app, open it in Airplane mode, and verify the gallery photos are completely gone (proving `cachePolicy="memory"` worked).

### Step 4.2 Product Intent

- This is the first slice that should feel like a real landlord and escort product rather than a pure security demonstrator.
- The implementation should therefore prioritize a credible owner dashboard, guest gallery experience, and anonymity-preserving moderation model over additional low-level proof-of-concept security screens.
- Any future admin or moderator dashboard that can inspect reporter identity is intentionally out of scope for Step 4.2 and must remain separate from the mobile app product surface.

### Step 4.2 Implementation Snapshot

- **Owner slice validated on device:** The landlord flow now supports session-derived role detection, flat creation with required title/description/phone/email fields, contact block rendering, physical-device photo upload, physical-device photo deletion, and landlord-side escort report submission with confirmation.
- **Backend and contract work in place:** The flat resource now exposes contact metadata, landlord and escort bootstrap responses now include actor metadata, and the backend validation contract enforces the required owner fields for flat creation.
- **Manual owner smoke test completed:** The owner flow has been exercised end to end on a physical iPhone, including create flat, upload photo, delete photo, and save an escort report from the product gallery surface.
- **Escort onboarding and guest-mode activation validated:** The escort OTP handshake now succeeds against the real backend contract, rejects invalid OTP input correctly, stores escort session metadata in secure storage, and flips the product gallery into escort mode on device.
- **Guest gallery UI rendering validated:** The guest-facing gallery surface now renders contact information, protected images, vote controls, and landlord-report controls while keeping owner-only create/upload/delete actions hidden.
- **Guest action submission validated on device:** The escort vote flow now saves real vote state successfully, and the escort landlord-report flow now saves real report state successfully from the guest gallery surface.
- **Gallery renderer aligned with the written target:** The flat-card surface now renders through `FlashList`, closing the remaining implementation gap between the validated MVP slice and the original Step 4.2 rendering plan.
- **Device mode is now role-locked for testers:** The app now persists a first-run `landlord` or `escort` device mode separately from the secure session, uses that lock to keep the UX readable for non-technical testers, and rejects OTP responses that do not match the selected mode.
- **Invitations are now generalized for both roles:** The invitation contract now supports landlord and escort invites through the same signed mobile flow, with backend validation for `invited_role` and optional `escort_external_id` when the target escort is known by a public ad identifier.
- **Report visibility policy is now explicit in product:** Landlords now see global landlord-report aggregates on flats, escorts keep their own vote/report state private, and the mobile surface does not reveal reporter identity to the opposite side.
- **Landlord-side reported-escort history is now gated:** A landlord must publish at least one flat before the app reveals the landlord-side reported-escort summary, reducing the chance of using the app as a pure report-browsing surface without listing participation.
- **Landlord-side reported-escort visibility is now global:** Once that gate is passed, landlords can see escort reports filed by any landlord, even when there is only one recorded report, while the product surface still hides which landlord submitted it.
- **Landlord escort reports are no longer blocked on app registration:** Landlord moderation now accepts the escort's public `amaterky.sk/<id>` ad id, preserves a link to an internal escort record only when one exists, and returns the external ad id in the landlord-side reported-escort summary.

### Step 4.2 Remaining Scope

- **Release-style cache verification is still deferred:** The implementation sets image rendering to `cachePolicy="memory"`, but the guest-side close-app and offline verification path is postponed to a later hardening pass rather than blocking MVP closure.
- **Advanced media UX is intentionally deferred:** Carousel browsing, pinch-to-zoom, and richer image-viewing interactions are product polish items for later phases, not Step 4.2 MVP requirements.

## Step 4.3: Field Test Readiness (UI Polish & Android Parity)

- **Status:** Partially completed for live production validation on May 15, 2026. The UI clean-up slice, Android signer parity, Android runtime validation, deployed-backend reachability, Android and iOS escort onboarding, and cross-role invitation recovery are now proven on physical devices. Broader closed-pilot rollout and the long-term escort identity redesign remain open.
- **Detailed Documentation:** See [ADR/4_3_field_test_readiness.md](ADR/4_3_field_test_readiness.md).
- **Action:** Strip all developer diagnostic noise from the UI to create a confident, non-technical user experience, and implement the pending Android platform layer to enable end-to-end MVP testing.
- **Platform Target:** Field testing must support both product contracts (Landlord and Escort) on both iOS and Android. Step 4.3 is not only about Android escorts; it is the parity and rollout-readiness step for the two-role mobile product across both platforms.
- **Task:**
  1. **UI Polish & De-noising (Cross-Platform):**
     - Remove all on-screen debug logs, raw JSON dumps, and bypass buttons.
     - Implement clean React Query `isLoading` states (e.g., subtle loading spinners or skeleton loaders) so the app doesn't look "frozen" during network requests.
     - Implement user-friendly error boundaries (e.g., replacing "AxiosError 500" with "Nepodarilo sa načítať údaje. Skúste to znova.")
     - Add clear "Empty States" (e.g., when a Landlord has 0 flats, show a friendly prompt with an "Add Flat" button instead of a blank white screen).
  2. **Android Cryptographic Parity (KeyMint):**
     - Execute the pending Android scope from Step 3.
     - Implement the Expo Local Module in Kotlin to generate `secp256r1` keys using the Android Hardware Keystore / KeyMint.
     - Ensure the Kotlin module matches the exact same **ADR 004 Cryptographic Contract** (PEM/SPKI public key format, DER-encoded signature, 5-part canonical payload) that iOS uses, so the Laravel backend verifies both platforms identically.
     - Validate that Android signing supports both landlord and escort onboarding plus all protected gallery actions, so the two mobile roles reach parity with the already validated iOS flows.
  3. **Android Security (`FLAG_SECURE`):**
     - Activate `expo-screen-capture` specifically for Android to enforce the hard OS-level block against screenshots and screen recordings, fulfilling the zero-data-at-rest physical promise.
  4. **Android Compilation (`.apk`):**
     - Configure `eas.json` to build a standalone Android `.apk` profile.
     - Run `eas build --platform android --profile preview` to generate the `.apk` file that can be directly shared and sideloaded by the Escorts, bypassing the Google Play Store censorship.
  - **Execution Order:**
    1. **UI cleanup first:** Remove developer-facing diagnostics, replace raw failure text with user-facing copy, and add clean loading and empty states before exposing the product to real testers.
    2. **Android signer parity second:** Complete the Kotlin KeyMint signer and prove it matches ADR 004 exactly before treating Android escorts as trusted actors in live flows.
    3. **Android screenshot blocking third:** Turn on Android `FLAG_SECURE` protection only after the Android secure-client path is functional, so capture blocking is validated on the real sensitive surfaces.
    4. **APK distribution fourth:** Produce the sideloadable Android preview build only after UI cleanup and Android security parity are complete, so testers receive a coherent and defensible build rather than a moving target.
    5. **Closed-pilot checklist last:** Start real-user testing only after the four implementation stages above are complete and a narrow pilot checklist is signed off.
  - **Closed-Pilot Checklist:**
    - Restrict the first rollout to a small invited group of landlords and escorts rather than a broad public release.
    - Define who can issue invites, who can revoke tester access, and how compromised sessions or devices will be disabled.
    - Confirm production or pilot environment settings for rate limiting, log redaction, queue health, storage cleanup, and incident response.
    - Prepare support and moderation procedures for sensitive reports, mistaken invites, abusive behavior, and urgent content removal.
    - Run one end-to-end cross-platform rehearsal with the exact pilot infrastructure before inviting real testers.
    - Do not expand beyond the closed pilot until the rehearsal and the first pilot cycle complete without security or operational regressions.
  - **Initial Escort Pilot Distribution Plan:**
    - Android escorts should install through a hosted preview APK link.
    - iOS escorts should install through TestFlight rather than a normal file-host download.
    - Start with exactly one trusted Android escort and one trusted iOS escort.
    - Only invite escorts whose `amaterky.sk` ad is active and publicly exposes the phone number at the time of invitation.
    - Run each escort onboarding while the team watches production queue-worker output and SMS-provider behavior live.
- **First Implementation Slice:** Start with the cross-platform UI cleanup path in the main mobile entry screen: remove developer-facing invitation and onboarding diagnostics, convert transport-oriented copy into product-facing language, and keep the first tester flow readable before Android parity work begins.
- **Test Plan:**
  - `manual_e2e_ui_cleanliness`: Verify no debug info is visible during the happy path or error paths.
  - `manual_e2e_android_onboarding`: Run the `.apk` on a physical Android device, complete the OTP flow, and verify Laravel successfully registers the KeyMint public key.
  - `manual_e2e_android_screenshot_block`: Attempt to take a screenshot on the Android device and verify the OS explicitly blocks it.
  - `manual_e2e_cross_platform_invite`: (The Ultimate Test) Landlord (iOS) creates a flat and sends an invite -> Escort (Android) receives SMS, onboards, and securely votes on the flat.

### Step 4.3 Implementation Snapshot

- **Main entry flow cleaned up:** The invitation, landlord access, escort OTP, and gallery entry screens now use product-facing copy instead of developer transport framing, and the most visible bypass-style diagnostics have been removed from the primary tester path.
- **Slovak copy now covers the main tester path:** The onboarding, invitation, and gallery shell copy is now localized into Slovak for the current real-user audience instead of leaving the core flow in English.
- **Cleaner gallery loading and empty states:** The flat gallery surface now shows explicit loading and empty states, better role-status messaging, and simpler landlord and escort action labels instead of debug-oriented feedback.
- **Android signer parity implemented:** The Expo local module `bkp-secure-signer` now includes an Android Kotlin implementation backed by Android Keystore / KeyMint-style APIs and exposes the same signer contract used on iOS.
- **Android onboarding and protected actions validated on a real device:** Manual device testing confirmed landlord bootstrap, escort OTP verification, invitation flow, gallery access, vote/report flows, and protected media actions all work on Android against the Laravel signature contract.
- **Android runtime path stabilized:** The Android path now runs with `newArchEnabled=false`, the secure signer module registered for Android, and the gallery surface rendered through a runtime-safe `FlatList` path so the app works reliably in the current Expo SDK 54 / React Native 0.81 setup.
- **Android screenshot blocking implemented with a development guard:** `expo-screen-capture` now protects Android non-development builds, while development builds intentionally allow screenshots so UI work and device debugging remain practical.
- **Physical-device install path proven:** The Android app was built, installed, and exercised successfully on a physical device, giving Step 4.3 a real parity checkpoint rather than emulator-only confidence.
- **Tester-facing role separation is in place:** The entry flow now starts with a persistent `I am landlord` / `I am escort` mode selector, offers a reset path for device-role recovery, and keeps the shared invite flow inside the verified path for both roles.
- **Product distribution remains unified by platform:** The current product decision is to keep one Android app and one iOS app with both roles inside each app. A landlord-versus-escort app split is intentionally deferred until real usage evidence justifies it.
- **Field-test moderation semantics were simplified:** Landlord moderation input now accepts either a raw escort ad id or a pasted `amaterky.sk/<id>` URL, matching the field reality that an escorted person may be known publicly without already being registered in the app.
- **Shared-hosting production backend is now live:** The Laravel deployment at `https://bkp-server.zafo-forum.sk` now serves the app successfully through the shared-hosting workaround, responds on `/up`, and returns JSON validation from `/api/verify`.
- **Production queue processing is now repo-tracked operationally:** the shared-hosting queue drain path now depends on the committed `scripts/queue-work-cron.php` entrypoint and the corresponding Websupport cron job rather than an undocumented server-only script.
- **Two-device landlord production rehearsal is complete:** Android and iOS each completed landlord OTP onboarding against the deployed backend, producing two separate verified landlord rows with distinct hardware public keys and device tokens.
- **Live queued SMS invitation delivery is now validated operationally:** Real production invitations now succeed through the cron-driven queue path and the deployed SMS provider configuration once queue health and config cache are correct.
- **Real escort onboarding is now validated on both platforms:** One trusted Android escort and one trusted iOS escort completed live ad-bound OTP onboarding against production, each using a public `amaterky.sk` ad with a visible phone number.
- **Cross-role invitation growth is now live:** The invitation backend was generalized so verified escorts can create landlord invitations, the production migration was deployed successfully, and the live escort-to-landlord invitation flow completed successfully end to end.
- **The current pilot lane is now broader than the initial escort-only rehearsal:** the product has already passed landlord-to-landlord, landlord-to-escort, escort onboarding, landlord reporting, escort reporting, and escort-to-landlord invitation validation on production.

### Step 4.3 Remaining Scope

- **Closed-pilot operations are still pending:** Tester invitation governance, revocation flow, incident handling, monitoring, and a formal pilot runbook still need their own rollout pass even though the core production flows are now proven.
- **UI polish is intentionally not finished:** The app is now functionally coherent and much less developer-facing, but spacing, visual rhythm, and section consistency are deferred to a later polish slice instead of blocking the Android parity checkpoint.
- **Production operations are not yet automated:** The current shared-hosting deployment still relies on explicit operational care for queue worker uptime and configuration-cache refreshes when SMS-provider settings change.
- **Production SMS configuration still needs one manual guardrail:** `SMSTOOLS_LOCAL_OVERRIDE_PHONE` must stay empty outside local development so production OTP delivery is not silently diverted.
- **Escort identity redesign is now a mandatory follow-up expansion:** The current escort contract is sufficient for field testing, but it is no longer considered a durable production identity model because escorts can rotate ad IDs and phone numbers over time.
- **Landlord report-visibility hardening is still future work:** The current `publish at least one flat first` gate is intentionally light for pilot use. A later anti-misuse hardening pass should require stronger landlord credibility signals, such as one flat confirmed by at least three registered escorts, before broader escort-report visibility is expanded.
- **Current landlord report visibility is intentionally broad after the first gate:** The present field-test contract exposes globally shared landlord-side escort reports immediately after a landlord publishes at least one flat. Any stricter threshold belongs to a later hardening pass rather than the current pilot contract.
- **A separate landlord-versus-escort app split is explicitly deferred:** revisit that only if real usage shows persistent role confusion, materially different acquisition channels, security-boundary needs, or release velocity problems that the current unified apps cannot absorb.

### Mandatory Post-Pilot Expansion: Stable Escort Identity With Alias History

- The product must move from a single-phone and single-ad escort contract to a stable internal escort identity with historical aliases.
- The database should keep one hidden internal escort row id as the durable subject of moderation, trust, and audit history.
- Public ad IDs and phone numbers should be modeled as mutable escort aliases rather than as the escort's primary key.
- The mobile and moderation surfaces should display ad IDs and phone numbers only; the internal escort row id stays hidden from normal product UI and may exist only in admin URLs, internal joins, or tooling.
- The first observed ad ID may be preserved as the earliest known alias for orientation and audit purposes, but it should not become the real database primary key because ad IDs can disappear, be replaced, or multiply.
- Escort authentication should become ad-bound and scraper-backed rather than relying on inviter-entered phone numbers as the trust source.
- The inviter should submit an ad ID or ad URL, the backend should scrape the current ad phone during invitation, and installation should validate again against the currently scraped phone before the device is bound.
- The data model should support:
  - multiple historical ad IDs for one escort,
  - multiple historical phone numbers for one escort,
  - active versus historical alias state,
  - attribution of how a new alias was learned, such as invitation flow, admin merge, or moderation review.
- OTP onboarding should bind the device through the phone number currently scraped from the active ad, but moderation and repeat-incident tracking must continue across the full alias history of that escort.
- Landlord-side access to moderation history should also evolve beyond the first simple flat-publication gate. After pilot learning, broader escort-report visibility should require stronger evidence that the landlord operates a real listing, for example a flat that has been confirmed by at least three registered escorts, so the moderation surface is harder to misuse for passive data harvesting.
- Once an escort is onboarded, the backend should inspect the ad at a controlled interval such as once per day so alias history and current activity remain fresh without sending a new OTP each time.
- Support for the paid-but-hidden ad state such as `Vypnutý zadávateľom` is expected, but it can remain a mandatory follow-up after the first implementation if the initial authentication boundary only supports ads that are fully active and publicly expose the phone number.
- This redesign is intentionally deferred until after successful live testing so the currently validated invitation, onboarding, and signed-request flows are not destabilized immediately before field use.
