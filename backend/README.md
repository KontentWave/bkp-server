<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Local Flat Snapshot Sync

Use `php artisan dev:sync-production-flats` to refresh the local `flats` and `flat_photos` tables from a separately configured production database connection.

Required environment variables:

- `PROD_SYNC_DB_DRIVER=pgsql` or `mysql` / `mariadb`
- `PROD_SYNC_DB_HOST`, `PROD_SYNC_DB_PORT`, `PROD_SYNC_DB_DATABASE`, `PROD_SYNC_DB_USERNAME`, `PROD_SYNC_DB_PASSWORD`

Alternative connection style:

- `PROD_SYNC_DB_URL`

Optional environment variables:

- `PROD_SYNC_DB_SCHEMA=public`
- `PROD_SYNC_DB_SSLMODE=prefer`
- `PROD_SYNC_DB_CHARSET=utf8` for PostgreSQL or `utf8mb4` for MySQL / MariaDB
- `PROD_SYNC_DB_COLLATION=utf8mb4_unicode_ci` for MySQL / MariaDB
- `PROD_SYNC_FLAT_PHOTO_ROOT=/mounted/production/storage/app/public`

Notes:

- The command is local-only and exits in production.
- It deletes local `flat_photos` and `flats` before importing the new snapshot.
- Remote landlords are mapped to local placeholder landlord accounts shaped like `prod-sync-landlord-{remoteId}`.
- Photo binaries are copied only when you pass `--copy-photos` and `PROD_SYNC_FLAT_PHOTO_ROOT` points at a readable production photo tree.

Examples:

```sh
php artisan dev:sync-production-flats
php artisan dev:sync-production-flats --chunk=100
php artisan dev:sync-production-flats --copy-photos
```

## Translation Proxy

The backend now exposes a public translation proxy at `POST /api/translate`. The proxy forwards requests to a configured LibreTranslate-compatible endpoint.

Required backend environment variables:

- `LIBRETRANSLATE_ENDPOINT`
- `LIBRETRANSLATE_API_KEY` when your provider requires one

Optional backend environment variables:

- `LIBRETRANSLATE_CONNECT_TIMEOUT=5`
- `LIBRETRANSLATE_TIMEOUT=20`
- `LIBRETRANSLATE_DEBUG_RESPONSE=false`

Production wiring example:

```env
LIBRETRANSLATE_ENDPOINT=https://translate.example.com/translate
LIBRETRANSLATE_API_KEY=
LIBRETRANSLATE_DEBUG_RESPONSE=false
```

Then point the mobile app at BKP instead of LibreTranslate directly:

```env
EXPO_PUBLIC_TRANSLATION_API_URL=https://bkp-server.zafo-forum.sk/api/translate
```

### Local LibreTranslate Container

For local development, run the included container recipe:

```sh
cd backend
docker compose -f docker-compose.libretranslate.yml up -d
```

That exposes LibreTranslate on port `5000`.

Local backend `.env` example:

```env
LIBRETRANSLATE_ENDPOINT=http://127.0.0.1:5000/translate
LIBRETRANSLATE_API_KEY=
LIBRETRANSLATE_DEBUG_RESPONSE=true
```

If the mobile device is using your local BKP backend over LAN or Windows portproxy exposure, keep the mobile translation URL pointing at BKP, not directly at the container:

```env
EXPO_PUBLIC_TRANSLATION_API_URL=http://192.168.1.50:8000/api/translate
```

### Debugging

- Proxy successes are logged as `translation.proxy.succeeded`.
- Proxy failures are logged as `translation.proxy.failed`.
- When `LIBRETRANSLATE_DEBUG_RESPONSE=true`, `/api/translate` also returns a `debug` block and the `X-Translation-Proxy: bkp-libretranslate` header.

### Flat Translation Cache Worker

For low-cost operation, production can cache translated flat fields and let a local worker pull pending jobs from BKP, translate them on your own machine, and push the results back.

Additional backend environment variables:

```env
FLAT_TRANSLATION_WORKER_TOKEN=replace-with-long-random-token
FLAT_TRANSLATION_TARGET_LANGUAGES=en,ru,es
FLAT_TRANSLATION_JOB_BATCH_SIZE=20
FLAT_TRANSLATION_UPSTREAM_BASE_URL=https://bkp-server.zafo-forum.sk
FLAT_TRANSLATION_PROVIDER_NAME=local-libretranslate
```

Worker endpoints:

- `GET /api/internal/flat-translation-jobs?limit=20`
- `POST /api/internal/flat-translation-jobs/{jobId}`

Both endpoints require the header:

```http
X-Translation-Worker-Token: <FLAT_TRANSLATION_WORKER_TOKEN>
```

The polling endpoint returns pending `(flat_id, field_name, language)` jobs with `source_text` and `source_hash`.

The completion endpoint accepts:

```json
{
    "source_hash": "...",
    "status": "ready",
    "translated_text": "...",
    "provider": "local-libretranslate"
}
```

or a failed result:

```json
{
    "source_hash": "...",
    "status": "failed",
    "failure_message": "timeout"
}
```

`FlatResource` now returns a `translations` object containing only ready translations whose `source_hash` still matches the current flat source text.

To process jobs from a local machine that already runs LibreTranslate, use the artisan command:

```sh
php artisan translations:sync-flat-cache --limit=20
```

That command is intended for cron. Example every minute:

```cron
* * * * * cd /home/marcel/projects/bkp-server/backend && php artisan translations:sync-flat-cache --limit=20 >> storage/logs/flat-translation-worker.log 2>&1
```

The command pulls pending jobs from `FLAT_TRANSLATION_UPSTREAM_BASE_URL`, translates them through the locally configured `LIBRETRANSLATE_ENDPOINT`, and posts either `ready` or `failed` back to production.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
