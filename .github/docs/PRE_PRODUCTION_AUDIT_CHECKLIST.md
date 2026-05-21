# Pre-Production Audit Checklist

This checklist is for the current BKP stack:

- Laravel 12 backend in `backend/`
- Expo / React Native mobile app in `apps/mobile-app/`
- Current product areas: invitations, OTP verification, landlord/escort role flows, gallery access, flat creation, photo upload, landlord moderation, reported escort summary, queue-backed jobs, and hardware-signed mobile requests

Use this as a working checklist before pre-production rollout. Mark each item only after there is concrete evidence: code review, test proof, manual verification, log check, or deployment confirmation.

## Security

- [ ] Verify all auth-protected backend routes require the correct actor and cannot be reached anonymously.
- [ ] Verify Sanctum bearer token handling is consistent across landlord and escort flows.
- [ ] Verify hardware-signature validation is enforced on all routes that are supposed to require it.
- [ ] Verify signature verification is bound to the authenticated token owner, not to user-controlled IDs in headers or body.
- [ ] Verify OTP verification cannot be replayed after successful use.
- [ ] Verify invitation verification always derives actor type from authoritative backend state, not from client input.
- [ ] Verify rate limiting exists and is appropriate for OTP send, OTP verify, invitation creation, login-like flows, and gallery-sensitive endpoints.
- [ ] Verify brute-force protection exists for OTP attempts and invitation abuse scenarios.
- [ ] Verify phone numbers, ad IDs, URLs, and uploaded filenames are validated server-side.
- [ ] Verify uploaded photo endpoints enforce ownership and role permissions on every read, upload, and delete operation.
- [ ] Verify protected photo URLs cannot be accessed without a valid token/signature combination where required.
- [ ] Verify no sensitive data is exposed in API error payloads.
- [ ] Verify no secrets, tokens, OTPs, or signature material are written to logs in plain text.
- [ ] Verify production `.env` values are present, correct, and not accidentally mirrored into docs, code, or client bundles.
- [ ] Verify CORS, allowed origins, app URL, and mobile API base URL settings match the actual deployment setup.
- [ ] Verify queue, Reverb, broadcast, and cron endpoints cannot be abused externally.
- [ ] Verify share-intent and deep-link input on mobile cannot bypass expected validation rules.
- [ ] Verify dependency audit results for backend Composer packages and mobile npm packages are reviewed and any critical issues are addressed.

## Reliability And Dependability

- [ ] Verify queue worker execution is reliable in the real hosting model, including cron-driven worker startup if used.
- [ ] Verify invitation SMS sending handles transient provider failures cleanly.
- [ ] Verify failed jobs are observable and recoverable.
- [ ] Verify retries are safe and idempotent for invitation sending and similar background tasks.
- [ ] Verify duplicate invitation creation cannot produce inconsistent state for the same phone/role flow.
- [ ] Verify the mobile app behaves acceptably when the backend is unavailable, slow, or returns non-200 responses.
- [ ] Verify reconnect / reload behavior does not corrupt local device session state.
- [ ] Verify stored device mode and stored token recovery work after app restart.
- [ ] Verify gallery fetch, vote, report, upload, and delete flows recover cleanly after network interruption.
- [ ] Verify pagination behaves correctly for empty, single-page, and multi-page datasets.
- [ ] Verify production logs, failed jobs, and app crash signals are actually monitored somewhere.
- [ ] Verify DB backup and restore procedure exists and has been tested on a non-production snapshot.
- [ ] Verify there is a rollback plan for both backend deploys and mobile release builds.

## Maintainability

- [ ] Identify controllers, screens, or components that have grown too large and should be scheduled for later extraction.
- [ ] Verify naming is consistent across backend resources, API fields, mobile types, and UI labels.
- [ ] Verify duplicated role-gating logic is minimized and centralized where practical.
- [ ] Verify permission decisions come from authoritative session/backend state rather than repeated ad hoc checks.
- [ ] Verify critical business rules are covered by tests rather than only manual checking.
- [ ] Verify new flows use the established QA scripts in backend and mobile repos.
- [ ] Verify docs under `.github/docs/` still reflect the current architecture and deployment reality.
- [ ] Verify dead UI copy, dead state, and dead helper logic are removed after recent UX simplifications.
- [ ] Verify TODOs, commented-out code, and temporary probes are reviewed and triaged.
- [ ] Verify config values and magic strings that control behavior are not scattered unnecessarily.

## Standardized Error Handling And Custom Exceptions

- [ ] Define and document the standard backend API error shape.
- [ ] Verify validation errors use a consistent format across endpoints.
- [ ] Verify authorization and authentication failures use consistent status codes and response structures.
- [ ] Verify domain/business-rule failures use explicit custom exceptions or equivalent structured handling where appropriate.
- [ ] Verify mobile error parsing does not rely on fragile string matching when structured API errors are available.
- [ ] Verify user-facing messages are understandable while logs retain enough engineering detail.
- [ ] Verify unexpected exceptions are logged with enough context to diagnose production incidents.
- [ ] Verify queue/job failures, upload failures, and signature failures all surface actionable diagnostics.
- [ ] Verify known backend exception types are mapped to stable user-facing behavior in the app.

## Optimization

- [ ] Check for obvious N+1 query risks in invitation, verification, gallery, report summary, and photo flows.
- [ ] Check that API payloads are not returning unnecessary fields for mobile screens.
- [ ] Check repeated mobile refetches are intentional and not duplicating expensive calls.
- [ ] Check protected image loading does not fetch or transform more than needed.
- [ ] Check large lists and pagination parameters are reasonable for expected pre-production usage.
- [ ] Check queue-backed work is used for slow side effects rather than blocking request/response paths.
- [ ] Check mobile rendering of gallery cards does not perform avoidable work during simple state changes.
- [ ] Check logs are informative without being excessively noisy in production.

## Future Expandability

- [ ] Verify current role model can evolve without breaking assumptions everywhere in the codebase.
- [ ] Verify invitation architecture can support future invitation types or moderation states without major rewrites.
- [ ] Verify API resources and mobile types can accept additive fields safely.
- [ ] Verify gallery flows are not overly coupled to a single landlord/escort assumption where future admin or moderator surfaces may appear.
- [ ] Verify report summary structures can handle larger datasets, more reasons, or richer metadata later.
- [ ] Verify storage and media handling choices will still be workable when photo volume grows.
- [ ] Verify current navigation/screen structure in the mobile app leaves room for future role-specific sections.
- [ ] Identify any current schema or enum decisions that would make future growth unnecessarily expensive.

## Legacy Code And Cleanup Risk

- [ ] Identify older or provisional code paths that were kept during product-direction changes.
- [ ] Identify dead or near-dead backend endpoints, client helpers, or UI branches.
- [ ] Identify placeholders, temporary compatibility code, and probes that should not reach production unnoticed.
- [ ] Identify mixed-language or inconsistent copy that is acceptable for field testing but not for release.
- [ ] Identify any remaining dev-only behavior that must never be reachable in production builds.
- [ ] Identify outdated assumptions from early security-demo stages that no longer fit the product.

## Backend-Specific Checks

- [ ] Verify `backend/.env` production values for DB, queue, mail/SMS, Sanctum, Reverb, and app URL are correct.
- [ ] Verify `php artisan config:cache`, `route:cache`, and related production commands are compatible with the current codebase.
- [ ] Verify migrations are reversible where practical and safe to run on the target DB.
- [ ] Verify failed queue jobs are persisted and reviewable.
- [ ] Verify cron / worker wrapper deployment files are committed only when intentionally part of the production strategy.
- [ ] Verify file storage permissions and photo-serving behavior work on the real host.
- [ ] Verify backend test coverage exists for invitation create/verify, auth hardening, flat gallery access, reports, and photo operations.

## Mobile-Specific Checks

- [ ] Verify production API base URL configuration is correct for Android and iOS builds.
- [ ] Verify share-intent flows work from the real apps and browsers users will actually use.
- [ ] Verify app reinstall, update, and token persistence behavior is acceptable.
- [ ] Verify error states and loading states do not trap the user in blocked flows.
- [ ] Verify collapsible or hidden sections still remain discoverable and usable.
- [ ] Verify image upload and image viewing work on real devices, not only in dev assumptions.
- [ ] Verify the app handles expired/invalid tokens by resetting state cleanly.
- [ ] Verify release builds do not expose dev-only text, local bypass behavior, or debug assumptions.

## Release Readiness Gate

- [ ] All critical security findings are fixed or explicitly accepted with reason.
- [ ] All critical reliability findings are fixed or mitigated.
- [ ] API error shape is documented and stable enough for the mobile client.
- [ ] Production config and secret handling are verified by an actual deployment checklist.
- [ ] Minimum manual test pass has been executed on real devices and real backend.
- [ ] Open audit findings are ranked as blocker, high, medium, or low.
- [ ] A short rollback and incident-response note exists for launch week.

## Evidence Log

- [ ] Security review completed
- [ ] Reliability review completed
- [ ] Error-handling review completed
- [ ] Maintainability review completed
- [ ] Expandability review completed
- [ ] Legacy cleanup review completed
- [ ] Backend verification pass completed
- [ ] Mobile verification pass completed
