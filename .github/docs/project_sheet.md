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
- Local validation completed through focused feature tests, successful PostgreSQL migrations, and a live end-to-end API flow against the PostgreSQL-backed Laravel server.

### Remaining Operational Notes

- Real SMS delivery still requires valid `VONAGE_KEY`, `VONAGE_SECRET`, and sender configuration.
- Continuous websocket and queued notification processing still require the relevant local processes to be running during manual testing.
