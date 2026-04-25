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
  4. **Context Menu Injection:** \* In `background.js`, use `chrome.contextMenus.create` to add a "Pozvať do BKP" option.
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
  4.  Wire the context-menu-driven invitation flow against the existing Laravel `/api/invitations` endpoint using the stored bearer token.
  4.  Add a dedicated browser-safe invitation endpoint because the current `/api/invitations` route is protected by `hardware.signature` and is not directly callable from the Chrome extension.
  5.  Wire the context-menu-driven invitation flow against that browser-safe endpoint using the stored bearer token.
  6.  Reserve the iOS extension directory only; do not couple its native extension code to the Chrome extension implementation.
- **Accessibility (ARIA):**
  - In `popup.html`, ensure the input field has `aria-label="Sanctum Bearer Token"` and the save button has `role="button"` and `tabindex="0"`.
- **Test Plan:**
  - `test_phone_number_sanitization`: Unit test the regex/function that cleans the highlighted text.
  - `manual_e2e_storage`: Verify pasting a token into the popup correctly saves to `chrome.storage.local`.
  - `manual_e2e_invitation_flow`: Load unpacked extension, highlight a mock number on the target URL, click the context menu, and verify via Laravel logs/DB that the `/api/invitations` endpoint received the authenticated request.

### Step 2.1 Notes

- The Chrome Trust Anchor is intentionally standalone because its runtime, packaging, permissions, and release flow are unrelated to the iOS share extension.
- The iOS Trust Anchor should remain inside the future React Native app because it is distributed as part of the iOS client rather than as an independent desktop/browser deliverable.
- For Step 2.1, bearer-token authentication is the practical bridge to the backend, but it needs a dedicated browser-safe invitation route because the current `/api/invitations` endpoint also requires hardware-backed signing.
