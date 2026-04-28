# ADR 004: Secure Client Signer Contract

- **Status:** Accepted
- **Date:** 2026-04-28
- **Scope:** Step 3 signer contract and backend/mobile cryptographic boundary

## Context

Step 3 introduces the first mobile clients that must satisfy the Laravel `hardware.signature` middleware with device-bound cryptographic keys. The backend contract is already live in `VerifyHardwareSignature` and therefore becomes the controlling boundary for the mobile implementation.

Three facts make this worth freezing before broader Step 3 implementation proceeds:

1. Signature verification already depends on a specific canonical payload shape.
2. Mobile native signing will be implemented separately on iOS and Android, but both platforms must produce signatures the same backend can verify.
3. The iOS share-invite flow from Step 2.2 is blocked on this exact contract, so drift here would invalidate both onboarding and invite submission work.

## Decision

Step 3 will treat the signer contract as a shared cross-platform boundary with the following requirements.

### Canonical Payload Contract

- The canonical payload is a newline-delimited string with exactly five fields in this order:
  1. `X-Hardware-Timestamp`
  2. `X-Hardware-Nonce`
  3. uppercased HTTP method
  4. normalized request path including query string when present
  5. lowercase hexadecimal `sha256` hash of the raw request body
- The request path must always begin with `/`.
- The query string, when present, is appended verbatim as `?query=value` to the normalized path.
- The body hash for an empty request body is the `sha256` hash of the empty string.

### Key And Signature Contract

- Both iOS and Android use the NIST P-256 curve (`secp256r1`).
- The mobile clients export the public key in PEM/SPKI format so Laravel can load it through OpenSSL.
- The native signer returns a base64 string representing the DER-encoded ECDSA signature bytes.
- The backend continues to call `openssl_verify(..., OPENSSL_ALGO_SHA256)`, so the native modules sign the canonical payload with SHA-256-bound ECDSA semantics.

### Storage Boundary

- The Sanctum bearer token may be stored in `expo-secure-store`.
- The hardware private key must never be exported into JavaScript or stored in `expo-secure-store`.
- The private key remains inside Secure Enclave on iOS and Android Keystore / KeyMint on Android.

### Platform Execution Boundary

- Shared TypeScript code owns canonical payload construction and request-header assembly.
- Native platform code owns key generation, public-key export, and signature production.
- The iOS share flow remains host-app signed: the share target hands sanitized payload into BKP, and the host app performs the signed `POST /api/invitations` request.

## Consequences

### Positive

- The backend/mobile cryptographic boundary becomes explicit and testable before the rest of Step 3 expands.
- iOS and Android can implement different native modules without diverging on request verification semantics.
- The Step 2.2 invite flow and Step 3 onboarding flow now depend on the same signer contract instead of separate assumptions.

### Tradeoffs

- The mobile clients are now committed to PEM/SPKI public-key export and DER-encoded signature output unless the backend contract is deliberately changed.
- Any future backend change to canonicalization, query-string handling, or signature encoding must be coordinated with both native modules.

## Validation

The first Step 3 validation slice should prove this contract in three layers:

1. TypeScript unit coverage for canonical payload construction and path normalization.
2. Native signer interface tests or harness checks confirming public-key export and base64 signature output shape.
3. End-to-end request verification against Laravel `VerifyHardwareSignature` using the exact headers and canonical payload defined here.

## Future Development Carry-Forwards

- Implement the shared TypeScript `SecureSigner` interface and canonical payload builder before onboarding UI and Reverb integration.
- Keep the signer contract stable unless there is a deliberate ADR update and a matching backend change.
- Add explicit interoperability checks so iOS and Android signatures are both proven against the same Laravel middleware path.
