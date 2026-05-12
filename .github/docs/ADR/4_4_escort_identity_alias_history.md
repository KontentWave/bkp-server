# ADR 008: Stable Escort Identity With Alias History

- **Status:** Accepted
- **Date:** 2026-05-06
- **Scope:** Post-pilot escort identity redesign for stable moderation, onboarding continuity, and historical alias tracking

## Context

The current field-test contract allows the system to invite and report escorts by phone number and public ad identifier, but that model is not durable enough for production use.

In practice, escorts may:

1. rotate to a new public ad id,
2. rotate to a new phone number,
3. keep the same phone number across multiple ad ids,
4. keep multiple ads live at the same time,
5. attempt to escape prior moderation history by reappearing under newly created public aliases.

That means the real product subject is not one public ad id and not one phone number. The durable subject is the internally tracked escort person record, while public ad ids and phone numbers are mutable aliases observed over time.

The currently implemented external-ad-id flow remains useful for field testing because it avoids blocking moderation on prior app registration, but it should be treated as an interim contract rather than the final identity model.

The stronger target contract is ad-bound and scraper-backed:

1. the inviter supplies an ad id or ad URL,
2. the backend scrapes the active ad page and extracts the current phone number,
3. the OTP is sent to that scraped number,
4. installation validates again against the active ad before the device is bound,
5. after onboarding, the system continues to inspect the ad on a controlled schedule so alias history stays current.

## Decision

Escort identity will use a stable hidden internal database id plus historical alias tables.

### Core Identity Rule

- `escorts.id` remains the stable internal primary key.
- This internal id is the durable subject for moderation history, audit trails, device binding, and future admin workflows.
- The internal id should stay hidden from normal product UI.
- If needed, it may appear only in internal URLs, admin tooling, or backend joins.

### Alias Rule

- Public ad ids and phone numbers are escort aliases, not the escort primary key.
- One escort may have many ad ids over time.
- One escort may have many phone numbers over time.
- One escort may temporarily have multiple active ad ids or phone numbers at once.

### Modeling Rule

- The first observed ad id may be recorded as the earliest known alias for orientation and audit history.
- It must not become the real database primary key.
- Using the first ad id as the real row id would make the system fragile because the ad id can disappear, be replaced, or stop being the best external reference.

### Product Visibility Rule

- Product surfaces should display ad ids and phone numbers only.
- They should not display the internal escort row id.
- History views should present all known ad ids and phone numbers that belong to that escort, with active versus historical state where useful.
- For landlords and normal mobile flows, the primary public-facing lookup and reporting key should be the `amaterky.sk` ad id.

### Onboarding Rule

- OTP onboarding should bind one device through the phone number currently scraped from the active ad.
- Invitation should be created from ad id or ad URL, not from an inviter-entered phone number as the trust source.
- Installation should validate again against the active ad before the device is bound.
- That active scraped phone number should attach to the same stable escort row rather than creating a new escort subject whenever the public alias changes.
- The first implementation may require the ad to be in a fully active public state where the phone number is visible.
- Support for the paid-but-hidden `Vypnutý zadávateľom` state is necessary, but may be implemented as a mandatory follow-up if the initial scrape-based activation flow cannot reliably validate that state.

### Ongoing Inspection Rule

- After onboarding, the backend should inspect the ad on a controlled cadence such as once per day.
- This periodic inspection exists to refresh known ad and phone aliases, detect changes, and preserve moderation continuity without paying for a new OTP on every check.
- A mismatch between the stored active phone and the newly scraped active phone should trigger review or a re-verification policy rather than silently rewriting trust state.

### Administrative Update Rule

- When admins learn about a new ad id or phone number for an existing escort, the system should add it as a new alias linked to the same escort row.
- New aliases should preserve provenance when practical, such as:
  - invitation flow,
  - ad scraper refresh,
  - admin update,
  - moderation discovery,
  - alias merge from prior duplicate records.

## Suggested Data Shape

The exact table names can change, but the conceptual shape should be:

- `escorts`
  - stable hidden internal row
- `escort_ad_ids`
  - `id`
  - `escort_id`
  - `ad_id`
  - `is_active`
  - `observed_from`
  - `observed_until`
  - `source`
- `escort_phone_numbers`
  - `id`
  - `escort_id`
  - `phone_number`
  - `is_active`
  - `observed_from`
  - `observed_until`
  - `source`

This ADR does not require these exact names, only the stable-subject plus mutable-alias structure.

## Consequences

### Positive

- Moderation can follow one escort across changing ads and phone numbers.
- Repeat-incident tracking becomes materially stronger.
- OTP onboarding remains possible while using the current active ad phone as the activation credential instead of trusting arbitrary manual phone entry.
- Product UI can stay understandable because it shows the external identifiers users recognize while the system keeps a stable hidden internal subject.

### Tradeoffs

- The model is more complex than the current single-phone and single-ad contract.
- Admin tooling or merge workflows become more important because alias assignment can be uncertain in real-world data.
- Existing invitation, verification, and report contracts will need a second-pass redesign after the pilot.
- Scraper-backed authentication depends on the target site's page structure and on the ad being in a state where the phone is observable.

## Rollout Guidance

- Do not block the current validated field test on this redesign unless the pilot immediately depends on strong repeat-offender continuity.
- Treat this as a mandatory next expansion after the first successful live checkpoint.
- When implementation starts, update invitation, verification, moderation, scraper refresh, and summary APIs together so alias-history semantics stay coherent across the stack.

## Future Development Carry-Forwards

- Prefer surrogate internal ids for durable trust subjects even when public identifiers feel easier in the short term.
- Keep alias history append-friendly and auditable.
- Preserve the distinction between public-facing identifiers and internal stable identity in both backend modeling and mobile/admin UX.
- Keep the `amaterky.sk` ad id as the main landlord-facing search and reporting identifier even though the real escort subject remains an internal hidden row.
