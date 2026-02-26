# AGENTS.md

## Cursor Cloud specific instructions

### Environment gotchas

- **Cloud agent secrets conflict**: The cloud agent injects `SENTRY_LARAVEL_DSN` and `APP_KEY` environment variables that override `.env` values and cause runtime errors. When running artisan commands or starting the dev server, use `env -u SENTRY_LARAVEL_DSN -u APP_KEY` to strip them. The `.bashrc` already has `unset SENTRY_LARAVEL_DSN` but `APP_KEY` must also be unset when starting services.
- **Starting the dev server**: Use `env -u SENTRY_LARAVEL_DSN -u APP_KEY php artisan serve --host=0.0.0.0 --port=8000` instead of bare `php artisan serve`.

### Running services

- See `CLAUDE.md` for standard build, dev, and test commands.
- The primary interface is the REST API (`/api/v1/*`). The web UI login page has a pre-existing missing `layouts.guest` view (legacy restaurant-to-banking migration artifact), so web login returns 500. Use the API for testing.
- To create an API token for testing: `env -u SENTRY_LARAVEL_DSN -u APP_KEY php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \$u->createToken('t')->plainTextToken;"` (seeded password: `password`).
- DB resets: `env -u SENTRY_LARAVEL_DSN -u APP_KEY php artisan migrate:fresh --seed --force`.

### Testing

- Run banking API tests: `env -u SENTRY_LARAVEL_DSN -u APP_KEY php artisan test --filter=ApiV1EndpointsTest` (25 tests, all pass).
- Run additional WhatsApp banking tests: `env -u SENTRY_LARAVEL_DSN -u APP_KEY php artisan test --filter=WhatsAppAdditionalImplementationsTest` (11 tests, all pass).
- Many legacy tests (referencing `MenuCategory`, `Table`, etc.) fail because the restaurant models were removed during the banking migration. These are pre-existing failures, not regressions.
- Lint check: `./vendor/bin/pint --test` (pre-existing style issues in the codebase; exit code 1 is expected).

### Seeded test accounts

| Email | Role | Password |
|---|---|---|
| admin@agenticbanking.com | admin | password |
| manager@agenticbanking.com | manager | password |
| john@example.com | customer | password |
| jane@example.com | customer | password |
| corporate@example.com | customer | password |
