# ADR 012: Multilingual Client and Flat Translation Architecture

- **Status:** Accepted
- **Date:** 2026-06-09
- **Scope:** Mobile UI localization, automatic flat-field translation, backend translation proxying, cached production translations, and local worker synchronization

## Context

The mobile product moved beyond a single-language Slovak pilot surface.

The current field-test needs four supported languages:

1. Slovak (`sk`) as the source and default product language,
2. English (`en`),
3. Russian (`ru`),
4. Spanish (`es`).

That requirement applies at two different layers:

1. core app-shell and interaction copy must be localized inside the mobile client,
2. landlord-created flat content such as `title` and `description` must be translated automatically for escorts and landlords reading the gallery in another language.

The first implementation path used runtime translation through a LibreTranslate-compatible endpoint. That solved the initial product need, but it introduced three operational problems for production:

1. it made translated flat content depend on a live translation request at read time,
2. it hid failures too easily until explicit diagnostics were added,
3. it risked unnecessary cost if every client session translated the same flat content repeatedly.

To keep the product multilingual without turning translation into a permanent runtime bottleneck, the architecture needed to separate:

- localized app UI copy,
- optional runtime translation fallback,
- production-ready cached flat translations.

## Decision

### Supported Language Rule

- The mobile product officially supports `sk`, `en`, `ru`, and `es`.
- Slovak remains the source/default language for authored flat content unless a later product decision changes the authoring contract.

### UI Localization Rule

- Core mobile UI copy is localized inside the app through the in-app i18n layer.
- Language selection is a client concern and must not depend on a backend round trip.
- Missing automatic translation must not prevent the app from working in Slovak.

### Flat Content Translation Rule

- Flat `title` and `description` are the primary automatically translated flat fields.
- The preferred production read path is:
  1. ready cached translation returned by `FlatResource`,
  2. runtime translation fallback when configured,
  3. original source text when no translation is available.
- The current cached production translation scope targets `en`, `ru`, and `es` because Slovak is the source language.

### Runtime Translation Rule

- The mobile client may translate text at runtime through `EXPO_PUBLIC_TRANSLATION_API_URL`.
- The preferred runtime endpoint is BKP's `POST /api/translate` proxy, though a direct LibreTranslate-compatible service remains technically possible.
- Runtime translation is mainly a fallback and diagnostics path once cached production translations are available.

### Backend Proxy Rule

- Laravel exposes `POST /api/translate` as a LibreTranslate-compatible proxy boundary.
- The proxy exists to keep mobile clients decoupled from a specific provider and to centralize logging, debugging, and provider configuration.
- Proxy configuration lives behind `LIBRETRANSLATE_ENDPOINT`, optional `LIBRETRANSLATE_API_KEY`, and timeout/debug settings.

### Cached Translation Rule

- Production stores translated flat field values in `flat_translations`.
- Only current `ready` translations whose `source_hash` still matches the active source text are exposed back to clients.
- `FlatResource` is the stable API boundary that returns those cached translations.

### Worker Synchronization Rule

- Translation generation for cached flat fields may run outside production on a local machine that already hosts LibreTranslate.
- Production owns the queue state and cached translation rows.
- The local worker pulls pending jobs from BKP, translates locally, and pushes `ready` or `failed` results back.
- Existing flats must be seeded once with `translations:backfill-flat-cache` before the worker can populate their cached translations.

### Diagnostics Rule

- Translation failures must be visible rather than silent.
- The mobile client should surface translation failure state to the user when automatic translation is configured but failing.
- The backend proxy should log success/failure events and optionally return debug metadata when explicitly enabled.

## Consequences

### Positive

- The app can present a multilingual UI without waiting on the network.
- Production flat translations become reusable cached data rather than repeated per-client work.
- Translation provider choice stays abstracted behind the BKP backend.
- Local LibreTranslate hosting can reduce recurring external translation cost.

### Tradeoffs

- The architecture is more complex than a pure client-side translation call.
- Some translation behavior now spans three layers:
  - mobile UI localization,
  - backend proxying,
  - cached worker-driven flat translations.
- Not every translated field necessarily uses the same path today; flat `title` and `description` are the primary cached production targets.

## Rollout Guidance

- For local or pilot runtime translation, configure the mobile app with `EXPO_PUBLIC_TRANSLATION_API_URL`.
- For production-like cached flat translations:
  - configure the Laravel proxy and worker environment,
  - seed old flats with `php artisan translations:backfill-flat-cache`,
  - run `php artisan translations:sync-flat-cache --limit=20` on the local worker host,
  - verify `FlatResource` returns `translations` for ready rows.
- When translation failures must be investigated, enable backend debug response mode deliberately and disable it again after diagnosis.

## Future Development Carry-Forwards

- Keep multilingual UI copy and translated flat content documented as related but separate concerns.
- Prefer cached flat translations for production user-facing content over permanent live translation on every read.
- If runtime translation remains visible for secondary fields or fallback paths, keep user-facing failure hints aligned with the active product contract.
- Preserve the BKP proxy boundary even if the underlying translation provider changes later.
