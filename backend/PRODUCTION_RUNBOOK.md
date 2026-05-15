# Production Runbook

## Current Release State

- Backend queue cron entrypoint is committed in `scripts/queue-work-cron.php`.
- Production queue processing depends on a Websupport cron job invoking that PHP script.
- Production SMS delivery must run with `SMSTOOLS_LOCAL_OVERRIDE_PHONE` empty.
- iOS TestFlight build validated: app version `1.0.0`, build number `3`.
- Escort onboarding has been validated on both Android and iOS against the production backend.

## Backend Operations

- Keep the Websupport cron enabled for the PHP file that runs `scripts/queue-work-cron.php`.
- Use `QUEUE_CONNECTION=database` in production.
- If OTP delivery stops, inspect pending rows in `jobs` first before changing application code.
- If SMS delivery behaves unexpectedly, verify `SMSTOOLS_API_KEY` and confirm `SMSTOOLS_LOCAL_OVERRIDE_PHONE` is blank.

## Verified Test Flow

- Landlord sends an escort invitation using the live ad URL.
- Backend scrapes the ad, resolves the current phone number, and creates a pending invitation.
- Queue worker sends the OTP through SMSTools.
- Escort verifies the OTP on the device.
- Backend accepts the invitation, creates or updates the escort, and issues a Sanctum token.

## Verified Production Example

- Verified ad external ID: `16739`
- Verified ad URL: `https://amaterky.sk/16739`
- Verified iOS release channel: TestFlight
- Verified backend host: `https://bkp-server.zafo-forum.sk`

## Change Discipline

- Do not change onboarding, scraper, queue, or SMS code without a new production issue.
- Prefer operational checks first: cron status, queued jobs, ad phone change, and SMS provider configuration.
