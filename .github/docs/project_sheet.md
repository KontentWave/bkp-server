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
