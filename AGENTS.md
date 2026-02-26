# AGENTS.md

## Cursor Cloud specific instructions

### Environment variable gotchas

The Cloud Agent VM injects OS-level env vars that clash with `.env` config (Sentry DSN, app key, DB path). Unset them before running artisan: <!-- pragma: allowlist secret -->

```bash
unset SENTRY_LARAVEL_DSN APP_KEY DB_CONNECTION DB_DATABASE # pragma: allowlist secret
```

Persisted in `~/.bashrc`. If artisan fails with a Sentry DSN validation error or hits the wrong DB, this is why.

### Services overview

Laravel 11 digital banking platform. See `CLAUDE.md` for architecture, build commands, and test instructions.

- **Required**: PHP 8.2+, Composer, Node.js 18+, SQLite (pre-installed in VM snapshot)
- **Optional**: Redis, WhatsApp Cloud API, Firebase FCM, Stripe, AI sidecar (port 8101)

### Running the application

Standard commands from `CLAUDE.md` apply. Use `composer dev` or:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Testing

- `php artisan test --filter=ApiV1EndpointsTest` - 25 core banking API tests (all pass).
- `composer test` - full suite; legacy restaurant-era tests fail (removed models) - pre-existing issue.
- `./vendor/bin/pint --test` - linter (90 pre-existing style issues).

### API authentication

Endpoints at `/api/v1/*` require Sanctum Bearer tokens. Generate via tinker:

```bash
php artisan tinker --execute="echo App\Models\User::where('email','john@example.com')->first()->createToken('test')->plainTextToken;"
```

Seeded users: `admin@agenticbanking.com`, `john@example.com` (password: `password`).
