# ADR 001: Core Infrastructure Laravel Server

- **Status:** Accepted
- **Date:** 2026-04-24
- **Scope:** Step 1 local development baseline

## Context

Step 1 establishes the backend foundation for invitation onboarding, OTP verification, hardware-backed request authentication, and private realtime messaging. The implementation had to satisfy the original Laravel backend goals while remaining practical for local development on a single-machine PostgreSQL setup.

The original plan required three core capabilities:

1. A Laravel API backed by PostgreSQL for landlords, escorts, and invitation state.
2. A generic SMS invitation flow using Vonage.
3. Hardware-backed authentication for protected REST and websocket authorization paths.

During implementation, two security gaps in the initial plan were resolved directly in Step 1 instead of being deferred:

1. Header-selected actors were replaced with authenticated Sanctum principals.
2. Request signing was extended with timestamp and nonce enforcement to prevent replay.

## Decision

The local Step 1 backend is implemented with the following architectural decisions.

### Framework And Runtime

- Use Laravel 12 in `backend/` as the backend service.
- Use PostgreSQL as the active local development database.
- Keep sqlite in the test harness for fast isolated PHPUnit execution.

### Data Model

- `landlords` stores the landlord public key and verification state.
- `escorts` stores the phone number and bound public key after OTP verification.
- `invitations` stores landlord ownership, phone number, invitation status, expiration, and a hashed OTP token.
- `hardware_request_nonces` stores used nonces per personal access token to block replayed requests.

### Authentication And Request Trust

- Use Laravel Sanctum bearer tokens as the authenticated device identity for landlords and escorts.
- Use elliptic curve public keys stored per actor for hardware signature verification.
- Require `X-Hardware-Signature`, `X-Hardware-Nonce`, and `X-Hardware-Timestamp` on protected routes.
- Verify a canonical payload of timestamp, nonce, method, path, and body hash with `openssl_verify`.
- Reject stale timestamps, malformed signatures, missing authenticated principals, and reused nonces with `401` responses.

### Invitation And Verification Flow

- `POST /api/landlords/tokens` exists as a local-only bootstrap endpoint for landlord device sessions.
- `POST /api/invitations` is guarded by `auth:sanctum` and `hardware.signature`.
- Invitation OTPs are generated as 4-digit codes and stored only as SHA-256 hashes.
- `POST /api/verify` accepts phone number, OTP, and escort public key, then issues a Sanctum token for the escort device.
- `POST /api/protected/ping` exists as the initial middleware-protected probe route for validating the signed-request path end to end.

### Messaging And Broadcasting

- Use `laravel/vonage-notification-channel` for generic invitation SMS delivery.
- Queue the invitation notification via `ShouldQueue`.
- Use Laravel Reverb for broadcast support.
- Gate broadcast authorization through `auth:sanctum` and `hardware.signature` so private channels are not authorized by session state alone.

## Consequences

### Positive

- The backend trust model is bound to authenticated principals rather than caller-supplied actor headers.
- Replay protection is part of the Step 1 baseline instead of a future hardening task.
- The local stack now matches the intended PostgreSQL deployment shape more closely than the original sqlite-first scaffold.
- Protected REST endpoints and broadcast auth share the same security posture.

### Tradeoffs

- A local bootstrap endpoint for landlord tokens exists only to enable development and automated testing. It must stay disabled outside local and testing environments.
- Real Vonage delivery is not fully validated until live credentials are supplied.
- Reverb and queue-backed notification delivery still depend on the relevant local processes being started during manual verification.

## Validation

Step 1 was validated with three layers of checks:

1. Focused feature tests covering invitation creation, OTP verification, signature acceptance and rejection, replay protection, and Reverb authentication.
2. Successful Laravel migration execution against the real local PostgreSQL database.
3. A live end-to-end HTTP flow against the running Laravel server using PostgreSQL, including landlord token issuance, signed invitation creation, OTP verification, escort token issuance, and a signed protected request.

## Operational Notes

- Local development keeps simple PostgreSQL credentials for convenience.
- Production should replace local bootstrap flows and trivial credentials with a stricter deployment model.
- Real SMS and realtime operations require valid provider credentials and running worker/server processes.

## Future Development Carry-Forwards

- Treat SMS OTP as onboarding proof of phone-number control only, not as a durable trust anchor for sensitive actions.
- Define a production retention and redaction policy for invitations, phone numbers, public keys, queue payloads, logs, and backups before processing live user data.
- Review queue payload exposure and logging defaults before production so notification metadata and invitation events do not leak through debug or worker logs.
- Keep the landlord bootstrap token endpoint restricted to `local` and `testing`; production needs a separate enrollment flow.
- Prepare explicit runbooks for queue workers and Reverb so manual and future staging validation use a consistent operational setup.
