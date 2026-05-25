# ADR 009: Pilot Distribution, Environment Routing, and OTP Lifetime

- **Status:** Accepted
- **Date:** 2026-05-21
- **Scope:** Late field-test decisions for local-versus-production routing, tester build behavior, production deployment expectations, platform distribution, and invitation OTP lifetime

## Context

By the time the product reached real landlord and escort rehearsals, the main technical flows were already working on both mobile platforms and against the deployed backend. The remaining risk moved away from signature verification and into rollout behavior:

1. Local development needed a realistic split where landlord behavior could continue to exercise the live backend while escort behavior remained testable against a local Laravel server.
2. Shared tester builds needed the opposite rule: both roles had to point at production so a downloaded build could not accidentally route escort traffic to a developer's local machine.
3. Production shared hosting exposed gallery media unreliably through `/storage`, so media delivery needed a backend-served route rather than an assumption of public storage availability.
4. Local realism improved materially when production flats, reports, votes, and gallery photos could be mirrored into a local environment for rehearsals.
5. OTP delivery that expired after 10 minutes was too short for real tester installation workflows, especially when mobile builds were large enough that download plus install time consumed a meaningful part of the activation window.
6. Android and iOS distribution diverged operationally:
   - Android preview APK distribution worked well through direct download.
   - iOS broad external distribution remained constrained by Apple's review and distribution policies.
7. EAS preview and production build profiles needed an explicit backend contract so role-aware local-development settings did not leak into shared tester artifacts.

These are durable rollout decisions rather than temporary implementation details, so they deserve their own ADR instead of being buried in task notes.

## Decision

### Environment Routing Rule

- Local mobile development uses role-aware backend routing:
  - `landlord` -> production Laravel
  - `escort` -> local Laravel
- Shared tester builds and production builds must not use that split.
- EAS preview and production profiles must inject production backend URLs for both roles:
  - `EXPO_PUBLIC_API_BASE_URL=https://bkp-server.zafo-forum.sk`
  - `EXPO_PUBLIC_API_BASE_URL_LANDLORD=https://bkp-server.zafo-forum.sk`
  - `EXPO_PUBLIC_API_BASE_URL_ESCORT=https://bkp-server.zafo-forum.sk`

### Production Media Delivery Rule

- Flat photos are delivered through the backend-controlled media endpoint rather than depending on public shared-hosting storage exposure.
- The mobile client should treat `content_url` as the stable gallery-media contract.
- Shared-hosting `/storage` availability must not be considered the production contract.

### Local Rehearsal Data Rule

- Local rehearsals may use mirrored production-like data rather than requiring entirely synthetic listings.
- The backend production-sync command is an accepted operational tool for copying flats, related moderation metadata, and optionally photo files into local development.
- Mirrored photo binaries remain local runtime data and are not source-controlled.

### Invitation OTP Lifetime Rule

- Invitation OTP lifetime defaults to 60 minutes.
- The value remains environment-configurable through `INVITATION_OTP_TTL_MINUTES`.
- Test environments that need strict phone-match behavior must pin `AMATERKY_ENFORCE_PHONE_MATCH=true` explicitly so local bypass flags do not leak into feature tests.

### Android Distribution Rule

- Android pilot distribution uses a standalone preview APK.
- The preview APK may be shared either through the Expo build page or a mirrored hosted download such as OneDrive.
- Android sideload distribution is considered the primary scalable pilot path for non-technical testers.

### iOS Distribution Rule

- iOS remains technically buildable but operationally constrained.
- Broad non-technical iPhone distribution is not assumed safe through external TestFlight or App Store review while reviewer-visible flows still reference `amaterky.sk` escort listings.
- Short-term iOS pilot options are limited to:
  - ad hoc or internal distribution to known devices,
  - narrowly controlled TestFlight usage with review-risk acceptance,
  - or a future Apple-safe product variant.

### Production Deployment Rule

- Backend deploys on shared hosting remain explicit Git-based pulls followed by cache clearing.
- After pulling backend changes that alter configuration or routes, the operational minimum is:
  - `php artisan optimize:clear`
  - `php artisan config:clear`
  - `php artisan route:clear`

## Consequences

### Positive

- Local development remains realistic without forcing every escort rehearsal onto the production backend.
- Shared tester builds have a clear backend contract and cannot accidentally route escort traffic to a local machine.
- Android now has a straightforward, low-friction pilot-distribution lane.
- OTP expiration better matches the real-world install-and-activate flow.
- Shared-hosting production media delivery is more stable because it no longer depends on unreliable public storage exposure.

### Tradeoffs

- The product now has two environment-routing modes to reason about:
  - local role-aware split
  - shared-build all-production routing
- iOS broad distribution remains a product-policy problem rather than a solved engineering problem.
- Production-like local data sync improves realism but adds one more operational tool that must stay clearly separated from source-controlled assets.
- The team must keep build-profile env values and runtime expectations aligned so local assumptions do not leak into shared artifacts.

## Rollout Guidance

- Use Android preview APKs for the broadest early pilot cohort.
- Treat iOS rollout as capacity-limited until an Apple-safe distribution strategy is chosen.
- Keep production backend deploy steps explicit and documented after every pushed backend change.
- Keep `INVITATION_OTP_TTL_MINUTES=60` explicit in production `.env` when practical, even though the code now defaults to 60.

## Future Development Carry-Forwards

- Preserve the separation between local-rehearsal routing and shared-build routing.
- Keep distribution policy decisions documented separately from core product implementation ADRs.
- Treat Apple review risk as a product-surface and go-to-market constraint, not only as a build-pipeline concern.
- If iOS broad rollout becomes a must-have, design a reviewer-safe iOS variant or alternate landlord access path rather than assuming external TestFlight approval.
