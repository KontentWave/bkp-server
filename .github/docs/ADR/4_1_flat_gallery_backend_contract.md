# ADR 005: Flat Gallery Backend Contract

- **Status:** Accepted
- **Date:** 2026-04-30
- **Scope:** Step 4.1 backend-first flat gallery contract and first mobile consumer boundary

## Context

Step 4.1 exists to freeze the landlord and escort flat-gallery contract before broader product UI work begins. The mobile client already has a validated Step 3 hardware-signing boundary, but until this step the gallery domain still lacked a stable data model, access rules, media-delivery contract, and write-action taxonomy.

This boundary needed to be recorded explicitly for five reasons:

1. The first mobile gallery consumer depends on a stable `GET /api/flats` response shape and cannot safely build against drifting resource fields.
2. Gallery media should remain protected by authenticated and hardware-signed delivery instead of regressing to public storage URLs.
3. Landlord and escort roles do not share the same permissions, so the access model must be frozen before product screens imply a broader public directory than intended.
4. Signed JSON requests and signed multipart uploads must remain under the same Step 3 verification model rather than creating a weaker file-upload exception.
5. The reporting model is intentionally bilateral and write-edit, which is a product decision that should be documented instead of being inferred from controller code.

## Decision

Step 4.1 treats the flat gallery as a backend-first contract with the following requirements.

### Domain Model

- `Flat` belongs to a `Landlord` and contains the user-facing fields `title` and optional `description`.
- `FlatPhoto` belongs to a `Flat` and stores backend-owned media metadata such as disk, path, filename, mime type, byte size, and sort order.
- `Vote` belongs to a `Flat` and an `Escort`, with one vote row per escort-flat pair.
- `FlatReport` supports both directions of reporting:
  - Escort reporting landlord
  - Landlord reporting escort
- Report uniqueness is pair-based and write-edit, not append-only. The same actor updates the existing report for the same flat and counterpart.

### Protected Endpoints

- `POST /api/flats` is landlord-only and creates a flat under the authenticated landlord.
- `GET /api/flats` is hardware-signed and paginated for both landlords and escorts.
- `POST /api/flats/{id}/photos` is landlord-only and accepts signed multipart uploads.
- `GET /api/photos/{id}/content` is the only supported gallery-media delivery path for the mobile client.
- `DELETE /api/photos/{id}` is landlord-only and owner-scoped.
- `POST /api/flats/{id}/vote` is escort-only.
- `POST /api/flats/{id}/report-landlord` is escort-only.
- `POST /api/flats/{id}/report-escort` is landlord-only.

### Access And Authorization Rules

- Landlords may list only their own flats.
- Escorts may list only flats reachable through the approved access rule based on accepted invitation linkage.
- Landlords may fetch protected photo content only for flats they own.
- Escorts may fetch protected photo content only for flats they are allowed to access.
- Landlords may report only escorts tied back through an accepted invitation relationship.
- Escorts may report only landlords tied to flats they can access.

### Pagination And Resource Shape

- `GET /api/flats` is a frozen paginated mobile contract.
- The backend response exposes mobile-facing resources rather than raw storage internals.
- Flat photo payloads expose `content_url` for protected backend delivery instead of public storage paths or disks.
- The landlord response sets `my_vote` to `null`; the escort response resolves `my_vote` from the escort's stored vote when present.

### Report Taxonomy

- Escort report reasons are fixed to:
  - `pimp`
  - `harassing`
  - `did_not_keep_agreement`
- Landlord report reasons are fixed to:
  - `drugs`
  - `hygiene`
  - `did_not_pay`

### Signed Request Boundary

- All protected gallery routes stay behind the existing Step 3 `VerifyHardwareSignature` middleware boundary.
- Multipart photo uploads must validate through the same request-signing model as JSON requests.
- The backend does not introduce a public or unsigned upload exception for gallery photos.

## Consequences

### Positive

- The first mobile gallery screen can depend on a stable list contract and stable role-scoped write actions.
- Media delivery remains private and backend-controlled.
- RBAC and access-scope expectations are explicit before broader UX work begins.
- Bilateral reports and their fixed reason taxonomies are now documented as part of the product contract, not just an implementation detail.

### Tradeoffs

- The escort success path is still constrained by the absence of in-app escort onboarding, so full device validation currently requires either seeded data or later Step 3 work.
- Keeping media fully protected adds backend complexity compared with public asset URLs.
- The contract now commits the product to paginated list semantics and write-edit report behavior unless a later ADR deliberately changes them.

## Validation

Step 4.1 is considered validated across four layers:

1. Focused Laravel feature coverage for pagination, signed multipart uploads, protected media delivery, owner-only mutations, vote behavior, and both reporting directions.
2. Middleware coverage proving raw multipart requests are still verified by the hardware-signature boundary.
3. Focused mobile unit coverage for the signed flat-list client and signed flat-action client.
4. Manual device verification showing:
   - hardware-signed `GET /api/flats` loads a paginated landlord-scoped list
   - a real flat card renders in the mobile app
   - landlord-side `report-escort` succeeds through the signed mobile path

## Future Development Carry-Forwards

- Keep the `GET /api/flats` response shape stable unless a later ADR deliberately revs the mobile contract.
- Preserve protected media delivery through `GET /api/photos/{id}/content` instead of reintroducing public URLs.
- Treat landlord and escort action asymmetry as intentional; do not collapse the two roles into a shared mutation surface without an explicit product decision.
- Use Step 4.2 and later UX work to build on this contract rather than redefining it inside the mobile app.
