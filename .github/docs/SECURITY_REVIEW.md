# BKP Security And Compliance Review

## High Risk Findings

1. The current product intent involves sexual-service adjacency, phone numbers, trust signals, and reporting data. That is a high-risk privacy and regulatory profile and needs legal review before release, especially for GDPR lawful basis, retention, subject access, and cross-border SMS processing.

2. The roadmap claim of "no data at rest" is not currently achievable as written. The server design necessarily stores phone numbers, invitation states, public keys, queue payloads, logs, and likely backups. The product language should be narrowed to "minimized retention" unless the backend design changes materially.

3. Hardware signatures without replay protection are insufficient. A valid captured request can be replayed unless each signed payload includes a nonce, timestamp, and server-side freshness enforcement.

4. SMS OTP proves temporary control of a phone number, not durable identity. SIM swap, number recycling, SMS interception, and forwarding all weaken the trust model. OTP should be treated as onboarding proof only, not as a lasting trust anchor.

## Medium Risk Findings

1. Reverb private channels are not yet fully hardware-gated end to end. A private channel definition exists, but the websocket authorization path still needs a dedicated hardware-aware auth flow.

2. Header-based actor lookup in the initial scaffold is acceptable for a prototype but not for production. The server should bind signatures to a real authenticated principal and reject mismatched actor identifiers.

3. Queue workers and notification logs can leak sensitive metadata if debug logging remains enabled. Queue payload encryption, log redaction, and production log policy should be defined before launch.

4. Mobile distribution goals that explicitly try to bypass app-store moderation create operational and platform-policy risk. That affects sustainability even if the software is technically functional.

## Recommended Controls

1. Add signed nonce, timestamp, and request canonicalization to the hardware signature protocol.
2. Define a retention matrix for invitations, reports, logs, SMS records, and public keys.
3. Encrypt sensitive database fields at rest where possible and disable verbose logging in production.
4. Separate onboarding trust from long-term account trust; require additional checks for sensitive actions.
5. Design a dedicated Reverb auth endpoint that enforces the same signature policy as protected API routes.
6. Obtain legal review before processing live user data.
