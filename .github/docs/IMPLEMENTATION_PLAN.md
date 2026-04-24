# BKP Laravel Step 1 Plan

## Goal

Deliver a Laravel backend in `backend/` that can create invitations, queue generic SMS notifications, verify OTP submissions, persist hardware public keys, and provide the first protected API surface for hardware-signed requests.

## Execution Order

1. Repository baseline
   - Keep the workspace root as the monorepo root.
   - Keep the Laravel server in `backend/`.
   - Commit the fresh Laravel scaffold separately from feature work.

2. Environment and infrastructure
   - Use Laravel 12 with PHP 8.4.
   - Target PostgreSQL as the production database.
   - Keep SQLite available for local smoke checks and fast test runs.
   - Set Vonage and Reverb credentials in `.env` before integration testing.

3. Data model
   - `landlords`: `id`, `public_key`, `is_verified`, timestamps.
   - `escorts`: `id`, `phone_number`, `public_key`, timestamps.
   - `invitations`: `id`, `landlord_id`, `phone_number`, `otp_token`, `status`, `expires_at`, timestamps.
   - Store `otp_token` as a one-way hash, not plaintext.

4. API surface
   - `POST /api/invitations`
   - `POST /api/verify`
   - `POST /api/protected/ping` as the initial middleware-protected probe route.

5. Notifications and queueing
   - Send generic invitation SMS through `InvitationSmsNotification`.
   - Keep the notification queued by implementing `ShouldQueue`.
   - Use a generic message that does not disclose the app domain.

6. Hardware signature enforcement
   - Require `X-Hardware-Signature`.
   - Resolve the key owner from request headers.
   - Verify the raw request body with the stored EC public key and `openssl_verify`.
   - Reject unsigned or invalid requests with `401`.

7. Reverb and broadcasting
   - Keep private channel definition in `routes/channels.php`.
   - Treat full hardware-gated broadcast auth as a follow-up hardening task.
   - Use Reverb env values locally so artisan and broadcasting boot cleanly.

8. Test coverage
   - Invitation creation queues a generic Vonage SMS.
   - OTP verification stores the public key.
   - Middleware accepts a valid EC signature.
   - Middleware rejects a tampered request.
   - Broadcast auth returns forbidden when unauthenticated.

## Recommended Next Increment

1. Switch the app from local SQLite to PostgreSQL in `.env`.
2. Add nonce and timestamp headers to the signature scheme to prevent replay.
3. Replace header-based actor lookup with proper authenticated principals.
4. Build dedicated broadcast auth that applies the same hardware verification to Reverb channel authorization.
